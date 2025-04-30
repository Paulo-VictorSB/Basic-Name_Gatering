<?php

namespace bng\Controllers;
use bng\Controllers\BaseController;
use bng\Models\AdminModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as WriterXlsx;

class Admin extends BaseController
{
    public function all_clients()
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin'){
            header("Location: index");
        }

        // get all clients from all agents
        $model = new AdminModel();
        $results = $model->get_all_clients();

        $data['user'] = $_SESSION['user'];
        $data['clients'] = $results->results;

        $this->view('layouts/html_header');
        $this->view('navbar', $data);
        $this->view('global_clients', $data);
        $this->view('footer');
        $this->view('layouts/html_footer');
    }

    public function export_clients_xlsx()
    {
        if (!check_session() || $_SESSION ['user']->profile != 'admin'){
            header("Location: index.php");
        }

        // get all clients
        $model = new AdminModel();
        $results = $model->get_all_clients();
        $results = $results->results;

        $header = ['name', 'gender', 'birthdate', 'email', 'phone', 'interests', 'created_at', 'updated_at'];
        $data[] = $header;

        // add header to collection
        foreach ($results as $client) {
            
            // remove the first property (id)
            unset($client->id);

            // add data as array (original $client is a stdClass object)
            $data[] = (array)$client;
        }

        // store the data nto the XLSX file
        $filename = 'output_' . time() . '.xlsx';
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $worksheet = new Worksheet($spreadsheet, 'dados');
        $spreadsheet->addSheet($worksheet);
        $worksheet->fromArray($data);

        $writer = new WriterXlsx($spreadsheet);
        header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheet.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
        $writer->save('php://output');

        // logger
        logger(get_active_user_name() . " - fez download da lista de clientes para o ficheiro: " . $filename . " | total: " . count($data) - 1 . " registros.");
    }
}