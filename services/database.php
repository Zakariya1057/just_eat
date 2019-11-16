<?php

    require_once __DIR__.'/../config/config.php';
    require_once __DIR__.'/../services/logger.php';

    class Database {

        public $connection;
    
        public function __construct(){
            global $logger;

            // $config = new config;
            print_r($config);
            return;
            // if($config->environment == 'live'){

            // }
            // else {

            // }

            $this->connection = 'null';
            $this->connection = new mysqli($config->dbhost, $config->dbuser, $config->dbpass, $config->dbname);
            if ($this->connection->connect_error){
                $logger->critical('Failed To Connect To Database');
                die("Failed to connect to database");
            } 
            else {
                $logger->debug('Successfully Connected To Database');
                return $this->connection;
            }
            
        }
    

        public function query($sql){

            global $logger;
            
            $results = $this->connection->query($sql);

            if($results){ 
                return $results;
            }

            $logger->critical('Query Error: '.$sql);
            throw new Exception("Error: $sql",404);  

        }


    }

?>