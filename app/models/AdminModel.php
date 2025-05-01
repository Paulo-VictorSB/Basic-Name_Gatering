<?php

namespace bng\Models;

use bng\Models\BaseModel;

class AdminModel extends BaseModel
{
    public function get_all_clients()
    {
        // Get all clients from all agents
        $this->db_connect();
        $results = $this->query(
            "SELECT " .
            "p.id, " .
            "AES_DECRYPT(p.name, '" . MYSQL_AES_KEY ."') name, " .
            "p.gender, " .
            "p.birthdate, " .
            "AES_DECRYPT(p.email, '" . MYSQL_AES_KEY ."') email, " .
            "AES_DECRYPT(p.phone, '" . MYSQL_AES_KEY ."') phone, " .
            "p.interests, " .
            "p.created_at, " .
            "AES_DECRYPT(a.name, '" . MYSQL_AES_KEY ."') agent " .
            "FROM persons p LEFT JOIN agents a " .
            "ON p.id_agent = a.id " .
            "WHERE p.deleted_at IS NULL " .
            "ORDER BY created_at DESC"
        );

        return $results; 
    }

    public function get_agents_clients_stats()
    {
        // get totals from agent's clients
        $sql =
            "SELECT * FROM (" .
            "SELECT " .
            "p.id_agent, " .
            "AES_DECRYPT(a.name, '" . MYSQL_AES_KEY . "') agente, " .
            "COUNT(*) total_clientes " .
            "FROM persons p " .
            "LEFT JOIN agents a " .
            "ON a.id = p.id_agent " .
            "WHERE p.deleted_at IS NULL " .
            "GROUP BY id_agent ) a " .
            "ORDER BY total_clientes DESC";

        $this->db_connect();
        $results = $this->query($sql);
        return $results->results;
    }

    public function get_global_stats()
    {
        // get global stats from the database

        // total agents
        $this->db_connect();
        $results['total_agents'] = $this->query("SELECT count(*) value FROM agents")->results[0];

        // total active clients
        $results['total_clients'] = $this->query("SELECT count(*) value FROM persons WHERE deleted_at IS NULL")->results[0];

        // total inactive clients (deleted)
        $results['total_deleted_clients'] = $this->query("SELECT count(*) value FROM persons WHERE deleted_at IS NOT NULL")->results[0];

        // average number of clients per agent
        $results['average_clients_per_agent'] = $this->query(
            "SELECT (total_persons / total_agents) value FROM " .
                "( " .
                "SELECT " .
                "(SELECT COUNT(*) FROM persons) total_persons, " .
                "(SELECT COUNT(*) FROM agents WHERE PROFILE = 'agent') total_agents " .
                ") a "
        )->results[0];

        // younger client
        $tmp = $this->query("SELECT TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) value FROM persons ORDER BY birthdate DESC LIMIT 1");
        if($tmp->affected_rows == 0){
            $results['younger_client'] = null;
        } else {
            $results['younger_client'] = $tmp->results[0];
        }

        // oldest client
        $tmp = $this->query("SELECT TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) value FROM persons ORDER BY birthdate ASC LIMIT 1");
        if($tmp->affected_rows == 0){
            $results['oldest_client'] = null;    
        } else {
            $results['oldest_client'] = $tmp->results[0];
        }

        // average age between all clients
        $results['average_age'] = $this->query("SELECT AVG(TIMESTAMPDIFF(YEAR,birthdate,CURDATE())) value FROM persons")->results[0];

        //percentage by gender - males
        $results['percentage_males'] = $this->query(
            "SELECT " .
                "CAST((total_males/total_clients) * 100 AS DECIMAL(5,2)) value " .
                "FROM " .
                "( " .
                "SELECT " .
                "(SELECT COUNT(*) FROM persons) total_clients, " .
                "(SELECT COUNT(*) FROM persons WHERE gender = 'm') total_males" .
                ") a "
        )->results[0];

        //percentage by gender - females
        $results['percentage_females'] = $this->query(
            "SELECT " .
                "CAST((total_females/total_clients) * 100 AS DECIMAL(5,2)) value " .
                "FROM " .
                "( " .
                "SELECT " .
                "(SELECT COUNT(*) FROM persons) total_clients, " .
                "(SELECT COUNT(*) FROM persons WHERE gender = 'f') total_females" .
                ") a"
        )->results[0];

        return $results;
    }
}