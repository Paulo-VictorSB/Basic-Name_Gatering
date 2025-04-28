<?php

namespace bng\Controllers;

use bng\Controllers\BaseController;
use bng\Models\Agents;

class Agent extends BaseController
{
    public function my_clients()
    {   
        if(!check_session() || $_SESSION['user']->profile != 'agent')
        {
            header('Location: index.php');
        }

        // get all agent clients
        $id_agent = $_SESSION['user']->id;
        $model = new Agents();
        $results = $model->get_agent_clients($id_agent);

        $data['user'] = $_SESSION['user'];
        $data['clients'] = $results['data'];

        $this->view('layouts/html_header');
        $this->view('navbar', $data);
        $this->view('agent_clients', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    public function edit_client($id)
    {
        if(!check_session() || $_SESSION["user"]->profile != "agent"){
            header("Location: index.php");
        }

        // check if the $id is valid
        $id_client = aes_decrypt($id);
        if(!$id_client){

            // id_client is invalid
            header("Location: index.php");
        }

        // loads the model to get the client's data
        $model = new Agents();
        $results = $model->get_client_data($id_client);

        // check if the client data exists
        if($results['status'] == 'error'){
            // invalid client data
            header("Location: index.php");
        }

        $data['client'] = $results['data'];
        $data['client']->birthdate = date('d-m-Y', strtotime($data['client']->birthdate));

        // display the edit client form
        $data['user'] = $_SESSION['user'];
        $data['flatpickr'] = true;  

        // check if there are validation errors
        if(!empty($_SESSION['validation_errors'])){
            $data['validation_errors'] = $_SESSION['validation_errors'];
            unset($_SESSION['validation_errors']);
        }

        // check if there are server errors
        if(!empty($_SESSION['server_error'])){
            $data['server_error'] = $_SESSION['server_error'];
            unset($_SESSION['server_error']);
        }

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('edit_client_frm', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    public function delete_client($id)
    {      
        $data = [];

        if(!check_session() || $_SESSION['user']->profile != 'agent')
        {
            header('Location: index.php');
        }

        // check if the $id is valid
        $id_client = aes_decrypt($id);
        if(!$id_client){
            // id_client is invalid
            header('Location: index.php');
        }

        // loads the model to get the client's data
        $model = new Agents();
        $results = $model->get_client_data(($id_client));

        if(empty($results['data'])){
            header('Location: index.php');
        }
        // display the view
        $data['user'] = $_SESSION['user'];
        $data['client'] = $results['data'];
        $this->view('layouts/html_header');
        $this->view('navbar', $data);
        $this->view('delete_client_confirmation', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    public function delete_client_confirm($id)
    {
        if(!check_session() || $_SESSION['user']->profile != 'agent')
        {
            header('Location: index.php');
        }

        // check if the $id is valid
        $id_client = aes_decrypt($id);
        if(!$id_client){
            // id_client is invalid
            header('Location: index.php');
        }

        // loads the model to delete the client's data
        $model = new Agents();
        $model->delete_client($id_client);

        // logger
        logger(get_active_user_name() . " - removeu o cliente: " . $id);

        // return to the agent's main page
        $this->my_clients();
    }

    public function new_client_frm()
    {
        if(!check_session() || $_SESSION['user']->profile != 'agent')
        {
            header('location: index.php');
        }

        $data['user'] = $_SESSION['user'];
        $data['flatpickr'] = true;

        // check if there are validation errors
        if(!empty($_SESSION['validation_errors'])){
            $data['validation_errors'] = $_SESSION['validation_errors'];
            unset($_SESSION['validation_errors']);
        }

        // check if there is a server erro
        if(!empty($_SESSION['server_error'])){
            $data['server_error'] = $_SESSION['server_error'];
            unset($_SESSION['server_error']);
        }

        $this->view('layouts/html_header', $data);
        $this->view('navbar', $data);
        $this->view('insert_client_frm', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    public function new_client_submit(){
        if(!check_session() || $_SESSION['user']->profile != 'agent' || $_SERVER['REQUEST_METHOD'] != 'POST')
        {
            header('location: index.php');
        }

        $validation_errors = [];

        // text name
        if(empty($_POST['text_name'])){
            $validation_errors[] = "Nome é de prenchimento obrigatório";
        } else {
            if(strlen($_POST['text_name']) < 3 || strlen($_POST['text_name']) > 50){
                $validation_errors[] = "O Nome precisa estar entre 3 e 50";
            }
        }

        // gender
        if(empty($_POST['radio_gender'])){
            $validation_errors[] = "O Gênero é de preenchimento obrigatório";
        }

        // birthdate
        if(empty($_POST['text_birthdate'])){
            $validation_errors[] = "A data de nascimento é obrigatória";
        } else {
            // check if this birthdate is valid and older than today
            $birthdate = \DateTime::createFromFormat('d-m-Y', $_POST['text_birthdate']);
            if(!$birthdate) {
                $validation_errors[] = "A data de nascimento não está no formato correto";
            } else {
                $today = new \DateTime();
                if($birthdate >= $today) {
                    $validation_errors[] = "A data de nascimento tem que ser menor que o dia atual";
                }
            }
        }

        // email
        if(empty($_POST['text_email'])){
            $validation_errors[] = "O email é de preenchimento obrigatório";
        } else {
            if(!filter_var($_POST['text_email'], FILTER_VALIDATE_EMAIL)){
                $validation_errors[] = "Email não é válido";
            }
        }

        // phone
        if(empty($_POST['text_phone'])){
            $validation_errors[] = "O telefone é de preenchimento obrigatório";
        } else {
            if(!preg_match("/^9{1}\d{8}$/", $_POST['text_phone'])){
                $validation_errors[] = "O telefone deve começar com 9 e ter 9 dígitos";
            }
        }

        // check if there are validation errors to return to the form
        if(!empty($validation_errors)){
            $_SESSION['validation_errors'] = $validation_errors;
            $this->new_client_frm();
            return;
        }

        // check if the client already existis with the same name
        $model = new Agents();
        $results = $model->check_if_client_exists($_POST);

        if($results['status']){

            // a person with the same name exists for this agent. Return server error
            $_SESSION['server_error'] = "Já existe um cliente com esse nome.";
            $this->new_client_frm();
            return;
        }

        // add new client to the database
        $model->add_new_client_to_database($_POST);

        // logger
        logger(get_active_user_name() . " - adicionou novo cliente: " . hash_text($_POST['text_name']) . "|" . hash_text($_POST['text_email']));

        // return to the main clients page
        $this->my_clients();
    }

    public function edit_client_submit()
    {
        if(!check_session() || $_SESSION['user']->profile != 'agent' || $_SERVER['REQUEST_METHOD'] != 'POST')
        {
            header('location: index.php');
        }

        $validation_errors = [];

        // text name
        if(empty($_POST['text_name'])){
            $validation_errors[] = "Nome é de prenchimento obrigatório";
        } else {
            if(strlen($_POST['text_name']) < 3 || strlen($_POST['text_name']) > 50){
                $validation_errors[] = "O Nome precisa estar entre 3 e 50";
            }
        }

        // gender
        if(empty($_POST['radio_gender'])){
            $validation_errors[] = "O Gênero é de preenchimento obrigatório";
        }

        // birthdate
        if(empty($_POST['text_birthdate'])){
            $validation_errors[] = "A data de nascimento é obrigatória";
        } else {
            // check if this birthdate is valid and older than today
            $birthdate = \DateTime::createFromFormat('d-m-Y', $_POST['text_birthdate']);
            if(!$birthdate) {
                $validation_errors[] = "A data de nascimento não está no formato correto";
            } else {
                $today = new \DateTime();
                if($birthdate >= $today) {
                    $validation_errors[] = "A data de nascimento tem que ser menor que o dia atual";
                }
            }
        }

        // email
        if(empty($_POST['text_email'])){
            $validation_errors[] = "O email é de preenchimento obrigatório";
        } else {
            if(!filter_var($_POST['text_email'], FILTER_VALIDATE_EMAIL)){
                $validation_errors[] = "Email não é válido";
            }
        }

        // phone
        if(empty($_POST['text_phone'])){
            $validation_errors[] = "O telefone é de preenchimento obrigatório";
        } else {
            if(!preg_match("/^9{1}\d{8}$/", $_POST['text_phone'])){
                $validation_errors[] = "O telefone deve começar com 9 e ter 9 dígitos";
            }
        }

        // check if the id_clients is present in POST and is valid
        if(empty($_POST['id_client'])){
            header("Location: index.php");
        }
        $id_client = aes_decrypt($_POST['id_client']);
        if(!$id_client){
            header("Location: index.php");
        }

        // check if there are validation errors to return to the form
        if(!empty($validation_errors)){
            $_SESSION['validation_errors'] = $validation_errors;
            $this->edit_client(aes_decrypt($id_client));
            return;
        }

        // check if there another agent's client with the same name
        $model = new Agents();
        $results = $model->check_other_client_with_same_name($id_client, $_POST['text_name']);

        // check if there is...
        if($results['status']){
            $_SESSION['server_error'] = "Já existe outro cliente com o mesmo nome";
            $this->edit_client($id_client, $_POST);
            return;
        }

        // update the agent's client data in the database
        $model->update_client_data($id_client, $_POST);
        

        logger(get_active_user_name() . "- atualizou dados do client ID" . aes_encrypt($id_client));

        // return to the main clients page
        $this->my_clients();
    }
}
