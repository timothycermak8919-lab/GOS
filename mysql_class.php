<?php
// class MySQL
class MySQL {
    private mysqli|false $conId = false;
    private string $host = '';
    private string $user = '';
    private string $password = '';
    private string $database = '';
    private mysqli_result|false $result = false;
    
    // constructor
    public function __construct(array $options = []) {
        // validate incoming parameters
        if (count($options) < 1) {
            trigger_error('No connection parameters were provided');
            exit();
        }
        foreach ($options as $parameter => $value) {
            if (!$parameter || !$value) {
                trigger_error('Invalid connection parameter');
                exit();
            }
            $this->{$parameter} = $value;
        }
        // connect to MySQL
        $this->connectDB();
    }
    
    // connect to MYSQL server and select database
    private function connectDB(): void {
        $this->conId = mysqli_connect($this->host, $this->user, $this->password);
        if (!$this->conId) {
            trigger_error('Error connecting to the server ' . mysqli_connect_error());
            exit();
        }
        if (!mysqli_select_db($this->conId, $this->database)) {
            trigger_error('Error selecting database ' . mysqli_error($this->conId));
            exit();
        }
    }
    
    // perform query
    public function query(string $query): void {
        if (!$this->result = mysqli_query($this->conId, $query)) {
            trigger_error('Error performing query ' . $query . ' ' . mysqli_error($this->conId));
            exit();
        }
    }
    
    // fetch row
    public function fetchRow(): array|false {
        return mysqli_fetch_array($this->result, MYSQL_ASSOC);
    }
    
    // count rows
    public function countRows(): int {
        if (!$rows = mysqli_num_rows($this->result)) {
            trigger_error('Error counting rows');
            exit();
        }
        return $rows;
    }
    
    // count affected rows
    public function countAffectedRows(): int {
        $rows = mysqli_affected_rows($this->conId);
        if ($rows < 0) {
            trigger_error('Error counting affected rows');
            exit();
        }
        return $rows;
    }
    
    // get ID from last inserted row
    public function getInsertID(): int {
        $id = mysqli_insert_id($this->conId);
        if ($id === false) {
            trigger_error('Error getting ID');
            exit();
        }
        return $id;
    }
    
    // seek row
    public function seekRow(int $row = 0): void {
        if (!mysqli_data_seek($this->result, $row)) {
            trigger_error('Error seeking data');
            exit();
        }
    }
}
