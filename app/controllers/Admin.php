<?php

namespace bng\Controllers;

use bng\Controllers\BaseController;
use bng\Models\AdminModel;
use bng\Models\Agents;
use bng\System\SendEmail;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as WriterXlsx;

class Admin extends BaseController
{
  public function all_clients()
  {
    // check if session has a user with admin profile
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
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
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
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

  public function stats()
  {
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
      header("Location: index.php");
    }

    // get all clients
    $model = new AdminModel();
    $data['agents'] = $model->get_agents_clients_stats();

    // display the stats page
    $data['user'] = $_SESSION['user'];

    // prepare data to chartjs
    if (count($data['agents']) != 0) {
      $labels_tmp = [];
      $totals_tmp = [];
      foreach ($data['agents'] as $agent) {
        $labels_tmp[] = $agent->agente;
        $totals_tmp[] = $agent->total_clientes;
      }
      $data['chart_labels'] = '["' . implode('","', $labels_tmp) . '"]';
      $data['chart_totals'] = '[' . implode(',', $totals_tmp) . ']';
      $data['chartjs'] = true;
    }

    // get global stats
    $data['global_stats'] = $model->get_global_stats();

    $this->view('layouts/html_header', $data);
    $this->view('navbar', $data);
    $this->view('stats', $data);
    $this->view('footer');
    $this->view('layouts/html_footer');
  }

  public function create_pdf_report()
  {
    // check if session has a user with admin profile
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
      header('Location: index.php');
    }

    // logger
    logger(get_active_user_name() . " - visualizou o PDF com o report estatístico.");

    // get totals from agent's clients and global stats
    $model = new AdminModel();
    $agents = $model->get_agents_clients_stats();
    $global_stats = $model->get_global_stats();

    // generate PDF file
    $pdf = new \Mpdf\Mpdf([
      'mode' => 'utf-8',
      'format' => 'A4',
      'orientation' => 'P'
    ]);

    $html = '
        <style>
          body {
            font-family: Arial, sans-serif;
            color: #333;
          }
          h2, h3 {
            margin: 0;
            padding: 0;
          }
          h2 {
            color: #2c3e50;
          }
          h3 {
            color: #34495e;
          }
          table {
            font-size: 12px;
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
          }
          th {
            background-color: #3498db;
            color: white;
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
          }
          td {
            padding: 8px;
            border: 1px solid #ddd;
          }
          tr:nth-child(even) {
            background-color: #f9f9f9;
          }
        </style>';

    // logo e título
    $html .= '<div style="position: absolute; left: 50px; top: 50px;">
                    <img src="assets/images/logo_32.png">
                  </div>';

    $html .= '<h2 style="position: absolute; left: 100px; top: 50px;">' . APP_NAME . '</h2>';

    // separador
    $html .= '<div style="position: absolute; left: 50px; top: 100px; width: 700px; height: 1px; background-color: #ccc;"></div>';

    // título do relatório
    $html .= '<h3 style="position: absolute; left: 50px; top: 130px; width: 700px; text-align: center;">
                    RELATÓRIO DE DADOS DE ' . date('d-m-Y') . '
                  </h3>';

    // Tabela de agentes
    $y = 180;
    $html .= '
        <div style="position: absolute; left: 140px; top: ' . $y . 'px; width: 500px;">
          <h3 style="text-align: center;">Resumo por Agente</h3>
          <table>
            <thead>
              <tr>
                <th>Agente</th>
                <th>N.º de Clientes</th>
              </tr>
            </thead>
            <tbody>';

    foreach ($agents as $agent) {
      $html .= '
              <tr>
                <td>' . htmlspecialchars($agent->agente) . '</td>
                <td style="text-align: center;">' . $agent->total_clientes . '</td>
              </tr>';
    }
    $html .= '
            </tbody>
          </table>
        </div>';

    // Tabela de estatísticas globais
    $y += (count($agents) * 30) + 100;
    $html .= '
        <div style="position: absolute; left: 140px; top: ' . $y . 'px; width: 500px;">
          <h3 style="text-align: center;">Estatísticas Gerais</h3>
          <table>
            <tbody>
              <tr><td>Total de agentes:</td><td style="text-align: right;">' . $global_stats['total_agents']->value . '</td></tr>
              <tr><td>Total de clientes:</td><td style="text-align: right;">' . $global_stats['total_clients']->value . '</td></tr>
              <tr><td>Clientes removidos:</td><td style="text-align: right;">' . $global_stats['total_deleted_clients']->value . '</td></tr>
              <tr><td>Média de clientes por agente:</td><td style="text-align: right;">' . sprintf("%.2f", $global_stats['average_clients_per_agent']->value) . '</td></tr>';

    if (empty($global_stats['younger_client']->value)) {
      $html .= '<tr><td>Cliente mais novo:</td><td style="text-align: right;">-</td></tr>';
    } else {
      $html .= '<tr><td>Cliente mais novo:</td><td style="text-align: right;">' . $global_stats['younger_client']->value . ' anos</td></tr>';
    }
    if (empty($global_stats['oldest_client']->value)) {
      $html .= '<tr><td>Cliente mais velho:</td><td style="text-align: right;">-</td></tr>';
    } else {
      $html .= '<tr><td>Cliente mais velho:</td><td style="text-align: right;">' . $global_stats['oldest_client']->value . ' anos</td></tr>';
    }

    $html .= '
              <tr><td>Homens (%):</td><td style="text-align: right;">' . $global_stats['percentage_males']->value . ' %</td></tr>
              <tr><td>Mulheres (%):</td><td style="text-align: right;">' . $global_stats['percentage_females']->value . ' %</td></tr>
            </tbody>
          </table>
        </div>';


    // -----------------------------------------------------------

    $pdf->WriteHTML($html);

    $pdf->Output();
  }

  public function agents_management()
  {
    // check if session has a user with admin profile
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
      header("Location: index.php");
    }

    // get agents
    $model = new AdminModel();
    $results = $model->get_agents_for_management();
    $data['agents'] = $results->results;

    $data['user'] = $_SESSION['user'];

    $this->view('layouts/html_header', $data);
    $this->view('navbar', $data);
    $this->view('agents_management', $data);
    $this->view('footer');
    $this->view('layouts/html_footer');
  }

  public function new_agent_frm()
  {
    // check if session has a user with admin profile
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
      header('Location: index.php');
    }

    $data['user'] = $_SESSION['user'];

    // check for validation error
    if (isset($_SESSION['validation_error'])) {
      $data['validation_error'] = $_SESSION['validation_error'];
      unset($_SESSION['validation_error']);
    }

    // check for server error
    if (isset($_SESSION['server_error'])) {
      $data['server_error'] = $_SESSION['server_error'];
      unset($_SESSION['server_error']);
    }

    $this->view('layouts/html_header', $data);
    $this->view('navbar', $data);
    $this->view('agents_add_new_frm', $data);
    $this->view('footer');
    $this->view('layouts/html_footer');
  }

  // =======================================================
  public function new_agent_submit()
  {
    // check if session has a user with admin profile
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
      header('Location: index.php');
    }

    // check if there was a post
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
      header('Location: index.php');
    }

    // form validation
    $validation_error = null;

    // check if agent is a valid email
    if (empty($_POST['text_name']) || !filter_var($_POST['text_name'], FILTER_VALIDATE_EMAIL)) {
      $validation_error = "O nome do agente deve ser um email válido.";
    }

    // check if profile is valid
    $valid_profiles = ['admin', 'agent'];
    if (empty($_POST['select_profile']) || !in_array($_POST['select_profile'], $valid_profiles)) {
      $validation_error = "O perfil selecionado é inválido.";
    }

    if (!empty($validation_error)) {
      $_SESSION['validation_error'] = $validation_error;
      $this->new_agent_frm();
      return;
    }

    // check if there is already a agent with the same username
    $model = new AdminModel();
    $results = $model->check_if_user_exists_with_the_same_name($_POST['text_name']);

    if ($results) {

      // there is an agent with that name (email)
      $_SESSION['server_error'] = "Já existe um agente com o mesmo nome.";
      $this->new_agent_frm();
      return;
    }

    // add new agent to the database
    $results = $model->add_new_agent($_POST);

    if ($results['status'] == 'error') {

      // logger
      logger(get_active_user_name() . " - aconteceu um erro na criação de novo registo de agente.");
      header('Location: index.php');
    }

    // send email with purl
    $url = explode('?', $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
    $url = $url[0] . '?ct=main&mt=define_password&purl=' . $results['purl'];
    $email = new SendEmail();
    $data = [
      'to' => $_POST['text_name'],
      'link' => $url
    ];

    $results = $email->send_email(APP_NAME . ' Conclusão do registo de agente', 'email_body_new_agent', $data);
    if ($results['status'] == 'error') {

      // logger
      logger(get_active_user_name() . " - não foi possível enviar o email para conclusão do registo: " . $_POST['text_name'] . ' - erro: ' . $results['message'], 'error');
      die($results['message']);
    }

    // logger
    logger(get_active_user_name() . " - enviado com sucesso email para conclusão do registo: " . $_POST['text_name']);

    // display the success page
    $data['user'] = $_SESSION['user'];
    $data['email'] = $_POST['text_name'];

    $this->view('layouts/html_header', $data);
    $this->view('navbar', $data);
    $this->view('agents_email_sent', $data);
    $this->view('footer');
    $this->view('layouts/html_footer');
  }

  public function edit_agent($id)
  {
    // check if session has a user with admin profile
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
      header('Location: index.php');
    }

    // check if id is valid
    if (empty($id)) {
      header('Location: index.php');
    }

    $id = aes_decrypt($id);
    if (!$id) {
      header('Location: index.php');
    }

    // get agents data
    $model = new AdminModel();
    $results = $model->get_agent_data($id);

    // validation error
    if (isset($_SESSION['validation_error'])) {
      $data['validation_error'] = $_SESSION['validation_error'];
      unset($_SESSION['validation_error']);
    }

    // server error
    if (isset($_SESSION['server_error'])) {
      $data['server_error'] = $_SESSION['server_error'];
      unset($_SESSION['server_error']);
    }

    $data['user'] = $_SESSION['user'];
    $data['agent'] = $results->results[0];

    // display the edit agent form
    $this->view('layouts/html_header', $data);
    $this->view('navbar', $data);
    $this->view('agents_edit_frm', $data);
    $this->view('footer');
    $this->view('layouts/html_footer');
  }

  // =======================================================
  public function edit_agent_submit()
  {
    // check if session has a user with admin profile
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
      header('Location: index.php');
    }

    // check if there was a post
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
      header('Location: index.php');
    }

    // check if id is present and valid
    if (empty($_POST['id'])) {
      header('Location: index.php');
    }

    $id = aes_decrypt($_POST['id']);
    if (!$id) {
      header('Location: index.php');
    }

    // form validation
    $validation_error = null;

    // check if agent is a valid email
    if (empty($_POST['text_name']) || !filter_var($_POST['text_name'], FILTER_VALIDATE_EMAIL)) {
      $validation_error = "O nome do agente deve ser um email válido.";
    }

    // check if profile is valid
    $valid_profiles = ['admin', 'agent'];
    if (empty($_POST['select_profile']) || !in_array($_POST['select_profile'], $valid_profiles)) {
      $validation_error = "O perfil selecionado é inválido.";
    }

    if (!empty($validation_error)) {
      $_SESSION['validation_error'] = $validation_error;
      $this->edit_agent(aes_encrypt($id));
      return;
    }

    // check if there is already another agent with the same username
    $model = new AdminModel();
    $results = $model->check_if_another_user_exists_with_same_name($id, $_POST['text_name']);

    if ($results) {

      // there is another agent with that name (email)
      $_SESSION['server_error'] = "Já existe outro agente com o mesmo nome.";
      $this->edit_agent(aes_encrypt($id));
      return;
    }

    // edit agent in the database
    $results = $model->edit_agent($id, $_POST);

    if ($results->status == 'error') {

      // logger
      logger(get_active_user_name() . " - aconteceu um erro na edição de dados do agente ID: $id", 'error');
      header('Location: index.php');
    } else {

      // logger
      logger(get_active_user_name() . " - editado com sucesso os dados do agente ID: $id - " . $_POST['text_name']);
    }

    // go to the main admin page
    $this->agents_management();
  }

  public function edit_delete($id = '')
  {
    // check if session has a user with admin profile
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
      header("Location: index.php");
    }

    // check if id is valid
    $id = aes_decrypt($id);
    if (!$id) {
      header("Location: index.php");
    }

    // get agent data
    $model = new AdminModel();
    $results = $model->get_agent_data_and_total_clients($id);

    // dispplay page for confirmation
    $data['user'] = $_SESSION['user'];
    $data['agent'] = $results->results[0];

    // display the edit agent form
    $this->view('layouts/html_header', $data);
    $this->view('navbar', $data);
    $this->view('agents_delete_confirmation', $data);
    $this->view('footer');
    $this->view('layouts/html_footer');
  }

  public function delete_agent_confirm($id = '')
  {
    // check if session has a user with admin profile
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
      header('Location: index.php');
    }

    // check if id is valid
    $id = aes_decrypt($id);
    if (!$id) {
      header('Location: index.php');
    }

    // delete agent (soft delete)
    $model = new AdminModel();
    $results = $model->delete_agent($id);

    if ($results->status == 'success') {

      // logger
      logger(get_active_user_name() . " - eliminado com sucesso o agente ID: $id");
    } else {

      // logger
      logger(get_active_user_name() . " - aconteceu um erro na eliminação do agente ID: $id", 'error');
    }

    // go to the main page
    $this->agents_management();
  }

  public function edit_recover($id = '')
  {
    // check if session has a user with admin profile
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
      header("Location: index.php");
    }

    // check if id is valid
    $id = aes_decrypt($id);
    if (!$id) {
      header("Location: index.php");
    }

    // get agent data
    $model = new AdminModel();
    $results = $model->get_agent_data_and_total_clients($id);

    // dispplay page for confirmation
    $data['user'] = $_SESSION['user'];
    $data['agent'] = $results->results[0];

    // display the edit agent form
    $this->view('layouts/html_header', $data);
    $this->view('navbar', $data);
    $this->view('agents_recover_confirmation', $data);
    $this->view('footer');
    $this->view('layouts/html_footer');
  }

  public function agents_recover_submit($id)
  {
    // check if session has a user with admin profile
    if (!check_session() || $_SESSION['user']->profile != 'admin') {
      header('Location: index.php');
    }

    // check if id is valid
    $id = aes_decrypt($id);
    if (!$id) {
      header('Location: index.php');
    }

    // delete agent (soft delete)
    $model = new AdminModel();
    $results = $model->recovery_agent($id);

    if ($results->status == 'success') {

      // logger
      logger(get_active_user_name() . " - recuperado com sucesso o agente ID: $id");
    } else {

      // logger
      logger(get_active_user_name() . " - aconteceu um erro na recuperação do agente ID: $id", 'error');
    }

    // go to the main page
    $this->agents_management();
  }

  public function export_agents_XLSX()
    {
        // check if session has a user with admin profile
        if (!check_session() || $_SESSION['user']->profile != 'admin') {
            header('Location: index.php');
        }

        // get agents data
        $model = new AdminModel();
        $results = $model->get_agents_data_and_total_clients();
        $results = $results->results;

        // add header to collection
        $data[] = ['name', 'profile', 'active', 'last login', 'created at', 'updated at', 'deleted at', 'total active clients', 'total deleted clients'];

        // place all agents in the $data collection
        foreach ($results as $agent) {

            // remove the first property (id)
            unset($agent->id);

            // add data as array (original $client is a stdClass object)
            $data[] = (array)$agent;
        }

        // store the data into the XSLX file
        $filename = 'output_' . time() . '.xlsx';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $worksheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'dados');
        $spreadsheet->addSheet($worksheet);
        $worksheet->fromArray($data);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
        $writer->save('php://output');

        // logger
        logger(get_active_user_name() . " - fez download da lista de agentes para o ficheiro: " . $filename . " | total: " . count($data) - 1 . " registos.");
    }
}
