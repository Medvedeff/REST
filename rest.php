<?php

// REST API for testing
//
//  $GET[_action] = read_row,create_row,update_row
//
class Rest extends CI_Controller {

    // controller constructor
    public function __construct()
    {
            parent::__construct();
    }

    public function dir() {
        $this->load->model('crud');
        echo 'rest:'.__DIR__.'; crud:'.$this->crud->dir();
    }

    //
    //  убираем ключи с пустыми значениями
    //    
    public static function removeEmpty($data) {
        foreach($data as $key=>$val) {
            if (empty($val) && $val!==0 && $val!=='0' && $val!=='NULL') {
                unset( $data[$key] );
            }
        }
        return $data;
    }
    
    //
    //  системные параметры (таблица, и т.п.) передаем по _table, _limit .. 
    //  остальные считаем как profile, username ...
    //  без подчеркивания
    //
    public static function separateParameters($params) {
        //
        //  убирает пустые параметры
        //

        $params = self::removeEmpty($params);
        
        // если не задан LIMIT, то устанавливаем его в 100
        if (@$limit = $params['_limit']) {
            unset($params['_limit']);
        } else {
            $limit = 100;
        }
        
        if (@$offset = $params['_offset']) {
            unset($params['_offset']);
        } else {
            $offset = 0;
        }
        
        if (@$action = $params['_action']) {
            unset($params['_action']);
        } else {
//            $action = 'read_row';
            $action = '';
        }
        
        if (@$table=$params['_table']) {
            unset($params['_table']);
        } else {
            die("Table not defined");
        }

        //
        if (@$db=$params['_db']) {
            unset($params['_db']);
        } else {
            $db = NULL;
//            die("DB not defined");
        }

        if (@$field=$params['_field']) {
            unset($params['_field']);
        } else {
            $field = '*';
        }
        
        if (@$where=$params['_where']) {
            unset($params['_where']);
        } else {
            $where = NULL;
        }

        if (@$order=$params['_order']) {
            unset($params['_order']);
        } else {
            $order = NULL;
        }
        
        
        
        return ['system'=>['db'=>$db,'table'=>$table,'field'=>$field,'limit'=>$limit,'offset'=>$offset,'action'=>$action],'params'=>$params,'where'=>$where,'order'=>$order];
//        return ['system'=>['db'=>$db,'table'=>$table,'field'=>$field,'limit'=>$limit,'action'=>$action],'params'=>$params];
    }

    //
    //  CREATE,READ,UPDATE
    //  _table,_limit, ...
    //
    public function index()
    {
        
        // decode
        $conf = $this->config->item('REST');
        // print_r($conf); // Array ( [url] => http://dev.infiplay.ru/rest [key] => infi20play16 )
        
        // check if the request comes from a white-listed ip
        $curip = $this->input->ip_address();
        if (!in_array($curip, $conf['ip_white_list'])) {
            $json = json_encode(array('error' => "IP {$curip} prohibited"));
            $this->output
                    ->set_content_type('application/json', 'utf-8')
                    ->set_status_header(401)
                    ->set_output($json);
            $this->output->_display();
            exit;
        }
        
        $this->load->helper('utilities');
        
        // print_r($_SERVER['QUERY_STRING']);

        // парсим QUERY_STRING в $get
        
        $enc = Infi::decode(@$_SERVER['QUERY_STRING'],$conf['key']);
        $res = parse_str( $enc,$get );
        
        //  string decoded in $get
        //        print_r($get);
        //  Array ( [email] => test@medvedev.ru [password] => 327681638 ) 
        //        exit;
        
        // profiler
        //$this->output->enable_profiler(TRUE);
        
        if (@($params = $get)) {
//        if (@($params = $_GET)) {

            // разбираем параметры get
            $sp = self::separateParameters($params);

            // грузим модель CRUD
            $this->load->model('crud');
            $this->crud->useDb($sp['system']['db']);
            
            // получаем системные данные в ['system']['table'], ['system']['limit']
            
            // есть POST-запрос на добавление либо изменение данных
            if (@$data = $_POST) {
                $data = self::removeEmpty($data);   // убираем пустые значения
            }
            // если пустой POST-запорс, то просто выводим данные
            if (empty($data)) {
                if ($sp['system']['action']=='read_row') {
                    
                    $res = $this->crud->read($sp['system']['table'],$sp['params'],$sp['system']['field'],$sp['system']['limit'],$sp['system']['offset'],$sp['where'],$sp['order']);

                    // $res = $this->crud->read($sp['system']['table'],$sp['params'],$sp['system']['field'],$sp['system']['limit']);
                    
                    $out = false;
                    foreach ($res->result() as $row)
                    {
                        $out[] = $row;
                    }
                    $json = json_encode($out);
                    // profiler
                    $sections = array(
                            'query_toggle_count'  => TRUE,
                            'queries' => TRUE
                    );
                    $this->output
                         //->set_profiler_sections($sections)
                         ->set_output($json)
                         ->_display();
                    exit;
                } else if($sp['system']['action']=='delete_row') {
                    $res = $this->crud->delete($sp['system']['table'],$sp['params']);
                    exit;
                }
            } else {
                // print_r( $data ); exit;
                //
                if (@$sp['params'] && $sp['system']['action']=='update_row') {               // если есть параметры, то делаем UPDATE
                    $res = $this->crud->update($sp['system']['table'],$sp['params'],$data);
                    $out = $res;
                    $json = json_encode($out);
                    echo $json;
                    exit;

                } else {                            // иначе INSERT
                    if ($sp['system']['action']=='create_row') {
                        $res = $this->crud->create($sp['system']['table'],$data);
                        $out = $res;
                        $json = json_encode($out);
                        echo $json;
                        exit;
                    }    
                }
            }
        }
    }
    
    //
    //  transaction
    //  _table,_limit, ...
    //
    public function trans()
    {
//        echo 'Trans';
        $res = false;
        if(@$post=$_POST) {
            $sql = $post['trans'];
            $trans = explode("\n", $sql);
            
            // грузим модель CRUD
            $this->load->model('crud');    //            $this->crud->useDb();
//            $this->load->model('crud');
            
//            print_r( $trans );
            $res = $this->crud->trans($trans);
            
        }
        echo json_encode($res);
    }
}
