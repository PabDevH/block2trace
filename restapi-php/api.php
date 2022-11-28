<?php
error_reporting(0);
require("/var/www/html/block2trace/config/config.php");

class api {
        function __construct() {
                $this->db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_DBNAME);
                $this->node = NODE;
                $this->nodefiles = NODEFILES;
                $this->createTSA = CREATETSA;
                $this->createIPFS = CREATEIPFS;
                $this->phpPATH = PHPPATH;
        }

        function object_to_array($obj) {
                $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
                foreach ($_arr as $key => $val) {
                        $val = (is_array($val) || is_object($val)) ? $this->object_to_array($val) : $val;
                                $arr[$key] = $val;
                }
                return $arr;
        }
        private function GetUserId($username) {
                $sql = "SELECT userId FROM users WHERE username='".$username."'";
                $res = mysqli_query($this->db, $sql);
                $dat = mysqli_fetch_array($res);
                return $dat['userId'];

        }
        function auth($username,$password) {
                $sql = "SELECT userId FROM users WHERE (username='".$username."' AND secret=MD5('".$password."'))";
		$res = mysqli_query($this->db, $sql);
                $dat = mysqli_fetch_array($res);
                return $dat['userId'];
        }
        function auth_error() {
                $this->SendHeader("401");
                $response['error']="Wrong username or password";
                echo json_encode($response, TRUE);
                exit;
        }
        function ShowOwnerAddress($username) {
                $sql = "SELECT public_key FROM users WHERE username='".$username."'";
                $res = mysqli_query($this->db, $sql);
                $dat = mysqli_fetch_array($res);
                return $dat['public_key'];
        }
        function GetMethod() {
                return $_SERVER['REQUEST_METHOD'];
        }
        function GetResource() {
                return $_SERVER['REQUEST_URI'];
        }
        function AvailableCommands($method) {
                if ($method=="GET") {
                        $commands = Array('GetDoc','GetNotValidated','CheckHistory');
                }
                if ($method=="POST") {
                        $commands = Array('UploadDoc','MassUploadDoc','UploadDocandCreateTSA','CreateTSA','CreateIPFS','CreateUser','AddAddress');
                }
                if ($method=="PATCH") {
                        $commands = Array("UpdateDoc");
                }
                return $commands;
        }
        function RequiredFields($action) {
                switch ($action) {
                        case 'AddAddress';
                                $requireFields=Array('hash');
                                return $requireFields;
                        break;
                        case 'UploadDoc':
                                $requireFields=Array('hash','documentdate','documentType');
                                return $requireFields;
                        break;
                        case 'CreateTSA':
                                $requireFields=Array('hash');
                                return $requireFields;
                        break;
                        case 'CreateIPFS':
                                $requireFields=Array('hash','Tsa');
                                return $requireFields;
                        break;
                        case 'UpdateDoc':
                                $requireFields=Array('tsa','documentdate','ipfs','tsa_hash');
                                return $requireFields;
                        break;
                        case 'CreateUser':
                                $requireFields=Array('name','lastname','email','username','secret');
                                return $requireFields;
                        break;
                }
        }
        function AvailableFields($action) {
                switch ($action) {
                        case 'UploadDoc':
                                $availableFields=Array('hash','documentdate','documentType','tsa','tsa_hash','ipfs');
                                return $availableFields;
                        break;
                }
        }
        function FieldTypes($action) {
                switch ($action) {
                        case 'UploadDoc':
                                $fieldTypes=Array('hash'=>'string','documentdate'=>'integer','documentType'=>'string','tsa'=>'string','tsa_hash'=>'string','ipfs'=>'string');
                                return $fieldTypes;
                        break;
                        case 'UpdateDoc':
                                $fieldTypes=Array('tsa'=>'string','documentdate'=>'integer','tsa_hash'=>'string','ipfs'=>'string');
                        break;
                }
        }
        function PreProcessQuery() {
                $method = $this->GetMethod();
                $resource = $this->GetResource();
                switch ($method) {
                        case 'GET':
                                $resource = explode("/",$resource);
                                $params = $resource;
                                $resource = $params[2];
                                if (in_array($resource,$this->AvailableCommands('GET'))) {
                                        $this->ProcessGET($resource,$params);
                                }else{
                                        $this->SendHeader('404');
                                }
                        break;
                        case 'POST':
                                $resource = explode("/",$resource);
                                $resource = $resource[2];
                                $params = (array) json_decode(file_get_contents("php://input"));
                                
                                if (in_array($resource,$this->AvailableCommands('POST'))) {
                                        $this->ProcessPOST($resource,$params);
                                }else{
                                        $this->SendHeader('404');
                                }
                        break;
                        case 'PATCH':
                                $resource = explode("/",$resource);
                                $id = $resource[3];
                                $resource = $resource[2];
                                $params = (array) json_decode(file_get_contents("php://input"));
                                if (in_array($resource,$this->AvailableCommands('PATCH'))) {
                                        $this->ProcessPATCH($resource,$id,$params);
                                }else{
                                        $this->SendHeader('404');
                                }
                        break;
                }
        }
        private function checHashinDb($hash) {
                $lowerhash=strtolower($hash);
                $sql = "SELECT pendingID FROM pendingDocs WHERE (lowerhash='".$lowerhash."' OR hash='".$hash."')";
                $res = mysqli_query($this->db, $sql);
                $dat = mysqli_fetch_array($res);
                $pendingID = $dat[0];
                if ($pendingID=="") $pendingID=0;
                return $pendingID;
        }
        private function parseResults($data) {
                $tmp=explode(":",$data);
                if ($tmp[1]!="") {
                        $tmp[1]=str_replace("'","",$tmp[1]);
                        $tmp[1]=str_replace(",","",$tmp[1]);
                        $tmp[1]=str_replace("[","",$tmp[1]);
                        return $tmp[1];
                }else{
                        $tmp=$data;
                        $tmp=str_replace("'","",$tmp);
                        $tmp=str_replace(",","",$tmp);
                        $tmp=str_replace("[","",$tmp);
                        $tmp=str_replace("]","",$tmp);
                        $tmp=str_replace(":","",$tmp);
                        return $tmp;
                }
        }
        function ProcessPATCH($resource,$id,$params) {
                header('Content-type: application/json');
                switch($resource) {
                        case 'UpdateDoc':
                                //hash, documentdate, tsa.
                                //Cuantas veces upgradeo, y si esta pending aun para no consultar el contrato saco de aca
                                $lowerhash = strtolower($id);
                                $sql = "SELECT processed, upgradeTimes,documentdate FROM pendingDocs WHERE lowerhash='".$lowerhash."'";
                                $res = mysqli_query($this->db, $sql);
                                $dat = mysqli_fetch_array($res);
                                $processed = $dat[0];
                                $upgradeTimes = $dat[1];
                                $documentdate = $dat[2];

                                if ($processed=="no") {
                                        //Esta pendiente , rechazo el upgrade.
                                        $this->SendHeader("417");
                                        $data['error']=1;
                                        $data['errormsg']="The hash ".$id." is still in queue and cannot be updated";
                                        echo json_encode($data, TRUE);
                                        exit;
                                }else{
                                        //Se puede upgradear
                                        if ($upgradeTimes>=2) {
                                                //Limite alcanzado
                                                $this->SendHeader("417");
                                                $data['error']=1;
                                                $data['errormsg']="Update limit reached for hash ".$id;
                                                echo json_encode($data, TRUE);
                                                exit;
                                        }else{
                                                /*
                                                        Parece estar todo bien
                                                        Reviso los tipos de campos enviados, y posterior que la fecha
                                                        del documento enviado sea posterior a la existente.
                                                */
                                                $required = $this->RequiredFields("UpdateDoc");

                                                foreach($params as $key => $val) {
                                                        if (!in_array($key,$required)) {

                                                                $this->SendHeader("417");
                                                                $data['error']=1;
                                                                $data['errormsg']="The field ".$key." does not exists";
                                                                echo json_encode($data, TRUE);
                                                                exit;
                                                        }
                                                }

                                                $fieldTypes = $this->FieldTypes("UpdateDoc");
                                                foreach($fieldTypes as $key => $val) {
                                                        if ($val=="integer") {
                                                                if (!is_integer($params[$key])) {
                                                                        $this->SendHeader("417");
                                                                        $data['error']=1;
                                                                        $data['errormsg']="The field ".$key." is not an integer";
                                                                        echo json_encode($data, TRUE);
                                                                        exit;
                                                                }
                                                        }elseif ($val=="string") {
                                                                if (!is_string($params[$key])) {
                                                                        $this->SendHeader("417");
                                                                        $data['error']=1;
                                                                        $data['errormsg']="The field ".$$key." is not an string";
                                                                        echo json_encode($data, TRUE);
                                                                        exit;
                                                                }
                                                        }
                                                }

                                                //Si ningun filtro de arriba lo freno, entonces ahora me fijo el tema de fechas
                                                if ($params['documentdate']>=$documentdate) {
                                                        //La caga...
                                                        $this->SendHeader("417");
                                                        $data['error']=1;
                                                        $data['errormsg']="The update date of the new document must be prior to the previous one.";
                                                        echo json_encode($data, TRUE);
                                                        exit;
                                                }else{
                                                        //Ahora si guardo....
                                                        $sql2 = "UPDATE pendingDocs SET documentdate='".$params['documentdate']."', tsa='".$params['tsa']."', tsahash='".$params['tsa_hash']."', ipfshash='".$params['ipfs']."' , upgrade='yes', processed='no', upgradeTimes=upgradeTimes+1 WHERE lowerhash='".$lowerhash."'";
                                                        echo $sql2;
                                                        $res2 = mysqli_query($this->db, $sql2);
                                                        $this->SendHeader("202");
                                                        $data['hash']=$id;
                                                        $data['error']="0";
                                                        $data['owner']=$this->ShowOwnerAddress($_SESSION['username']);
                                                        $data['errormsg']="It is queued to be processed";
                                                        echo json_encode($data, TRUE);
                                                        exit;
                                                }

                                        }
                                }
                                /*
                                echo "Hash a cambiar (Viene del uri): ".$id."\n";
                                echo "Campos enviados (json en body:)\n";
                                print_r($params);
                                */
                        break;
                }
        }
        function ProcessPOST($resource,$params,$return=0) {
                //Pongo el encabezado porque siempre va a salir un json
                header('Content-type: application/json');
                switch ($resource) {
                        case 'AddAddress':
                                
                                $required = $this->RequiredFields("AddAddress");
                                
                                //$requireFields=Array('name','lastname','email','username','secret');
                                //cantidad
                                if (count($params)<count($required)) {
                                        $this->SendHeader("417");
                                        $data['error']=1;
                                        $data['errormsg']="Required fields are incomplete, received ".count($params);
                                        echo json_encode($data, TRUE);
                                        exit;
                                }
                                //requeridos incluidos
                                foreach($required as $key) {
                                        if ($params[$key]) {
                                                $tmp = 0;
                                        }else{
                                                $tmp = 1;
                                                $this->SendHeader("417");
                                                $data['error']=1;
                                                $data['errormsg']=$key." field ??????";
                                                echo json_encode($data, TRUE);
                                                exit;
                                        }

                                }
                                //campos extras
                                foreach($params as $key => $val) {
                                        if (!in_array($key,$required)) {
                                                $this->SendHeader("417");
                                                $data['error']=1;
                                                $data['errormsg']="The field ".$key." does not exists";
                                                echo json_encode($data, TRUE);
                                                exit;
                                        }
                                }
                                $hash = base64_decode($params['hash']);
                                
                                $arr = explode( PHP_EOL , $hash );
                                
                                if ($arr[0]=="ADDRESSES") {
                                        for ($j=0; $j<count($arr); $j++) {
                                                if ($j>0) {
                                                        $tmp = explode(",",$arr[$j]);
                                                        $pubKey=$tmp[0];
                                                        $privKey=$tmp[1];
                                                        $sql = "SELECT public_key FROM addresses WHERE public_key='".$pubKey."'";
                                                        $res = mysqli_query($this->db, $sql);
                                                        $dat = mysqli_fetch_array($res);
                                                        if ($dat=="") {
                                                                $LenpubKey = strlen($pubKey);
                                                                $LenprivKey = strlen($privKey);
                                                                
                                                                
                                                                if ($LenpubKey==42 && $LenprivKey==64) {
                                                                        $sql2 = "INSERT INTO addresses(public_key,private_key) values('".$pubKey."','".$privKey."')";
                                                                        $res2 = mysqli_query($this->db, $sql2);
                                                                        $OkAddress .= $pubKey." ";
                                                                }else{
                                                                        //Prevent empty fields in array
                                                                        if ($pubKey!="" && $privKey!="") {
                                                                                $this->SendHeader("417");
                                                                                $data['error']=1;
                                                                                $data['errormsg']="Incorrect DATA (public or private key)";
                                                                                echo json_encode($data, TRUE);
                                                                                exit;
                                                                        }
                                                                }
                                                        }else{
                                                                $errAddress .= $pubKey." ";
                                                        }
                                                }
                                        }
                                }else{
                                        $this->SendHeader("417");
                                        $data['error']=1;
                                        $data['errormsg']="Invalid Hash";
                                        echo json_encode($data, TRUE);
                                        exit;
                                }
                                
                                $this->SendHeader("202");
                                $data['error']=0;
                                $data['Addresses_OK']=$OkAddress;
                                $data['Addresses_ERROR']=$errAddress;
                                echo json_encode($data,TRUE);
                        break;
                        case 'CreateUser':
                                $required = $this->RequiredFields("CreateUser");
                                //$requireFields=Array('name','lastname','email','username','secret');
                                //cantidad
                                if (count($params)<count($required)) {
                                        $this->SendHeader("417");
                                        $data['error']=1;
                                        $data['errormsg']="Required fields are incomplete, received ".count($params);
                                        echo json_encode($data, TRUE);
                                        exit;
                                }
                                //requeridos incluidos
                                foreach($required as $key) {
                                        if ($params[$key]) {
                                                $tmp = 0;
                                        }else{
                                                $tmp = 1;
                                                $this->SendHeader("417");
                                                $data['error']=1;
                                                $data['errormsg']=$key." field ??????";
                                                echo json_encode($data, TRUE);
                                                exit;
                                        }

                                }
                                //campos extras
                                foreach($params as $key => $val) {
                                        if (!in_array($key,$required)) {
                                                $this->SendHeader("417");
                                                $data['error']=1;
                                                $data['errormsg']="The field ".$key." does not exists";
                                                echo json_encode($data, TRUE);
                                                exit;
                                        }
                                }
                                $sql = "SELECT userId FROM users WHERE username='".$params['username']."'";
                                $res = mysqli_query($this->db, $sql);
                                $dat = mysqli_fetch_array($res);
                                $tmpUserId = $dat[0];
                                if ($tmpUserId>0) {
                                        //Ya existe el usuario
                                        $this->SendHeader("417");
                                        $data['error']=1;
                                        $data['errormsg']="Username already exists";
                                        echo json_encode($data, TRUE);
                                        exit;
                                }else{
                                        //No existe lo doy de alta
                                        /*
                                        root@web04:/var/www/node/block2trace# node createAddress.js
                                        0x1853142fae51f73fe36ab9f648b6d4cf833d89b7,0xfd804103c341c93379e51eec22f3bd11b3bacb85bca6ad7f7d8ebdb7d3546867
                                        
                                        exec($this->node." ".$this->nodefiles."createAddress.js 2>&1 &",$result);
                                        $response = $result[0];
                                        $response = explode(",",$response);
                                        $pubKey = $response[0];
                                        $privKey = $response[1]; 
                                        */
                                        $sql = "SELECT t1.public_key,t1.private_key FROM addresses t1 LEFT JOIN users t2 ON t2.public_key=t1.public_key WHERE t2.public_key IS NULL limit 0,1";
                                        $res = mysqli_query($this->db, $sql);
                                        $dat = mysqli_fetch_array($res);
                                        if ($dat[0]!="") {
                                                $pubKey=$dat[0];
                                                $privKey=$dat[1];
                                                $sql = "INSERT INTO users (name,lastname,email,username,secret,public_key,private_key) values('".$params['name']."','".$params['lastname']."','".$params['email']."','".$params['username']."',MD5('".$params['secret']."'),'".$pubKey."','".$privKey."')";
                                                $res = mysqli_query($this->db, $sql);
                                                $this->SendHeader("202");
                                                $data['error']=0;
                                                $data['response']['username']=$params['username'];
                                                $data['response']['publicKey']=$pubKey;
                                                $data['response']['errormsg']='Success';
                                                echo json_encode($data, TRUE);
                                                exit;
                                        }else{
                                                $this->SendHeader("417");
                                                $data['error']=1;
                                                $data['response']="There are no more public keys available. Submit a request for new addresses to your administrator.";
                                                echo json_encode($data, TRUE);
                                                exit;
                                        }
                                }
                        break;
                        case 'MassUploadDoc':
                                //Cuento totales
                                
                                $hash_total = count($params['hash']);
                                $required = $this->RequiredFields("UploadDoc");
                                $available = $this->AvailableFields("UploadDoc");
                                foreach($available as $key) {
                                        $tmp_avail_fields .= $key.",";
                                }
                                $tmp_avail_fields = substr($tmp_avail_fields,0,-1);

                                if (count($params)<count($required)) {
                                        $this->SendHeader("417");
                                        $data['error']=1;
                                        $data['errormsg']="Required fields are incomplete, received ".count($params);
                                        echo json_encode($data, TRUE);
                                        exit;
                                }
                                //Busco que no envie duplicados
                                $unique = array_unique($params['hash']);
                                $duplicates = array_diff_assoc($params['hash'], $unique);
                                if (count($duplicates)>0) {
                                        $this->SendHeader("417");
                                        $data['error']=1;
                                        $data['errormsg']="Some values are duplicated";
                                        $data['errordesc']=$duplicates;
                                        echo json_encode($data,TRUE);
                                        exit;
                                }
                                
                                for ($j=0; $j<$hash_total;$j++) {
                                        $ValidHash = $this->checHashinDb($params['hash'][$j]);
                                        if ($ValidHash>0) {
                                                $this->SendHeader("417");
                                                $data['error']=1;
                                                $data['errormsg']=$params['hash'][$j]." Already exists in DataBase";
                                                echo json_encode($data,TRUE);
                                                exit;
                                        }
                                }

                                foreach($available as $key) {
                                        if (count($params[$key])!=$hash_total) {
                                                $this->SendHeader("417");
                                                $data['error']=1;
                                                $data['errormsg']="The number of fields is different for each element.";
                                                $data['errordesc']="API expects a total of ".$hash_total." items for each required and available field in this function. (".$tmp_avail_fields.") If you need submit an empty field use '' for example {'field_empty_name':['','','']} ";
                                                echo json_encode($data,TRUE);
                                                exit;
                                        }
                                }
                                
                                for ($j=0; $j<$hash_total;$j++) {
                                        $tmp_params['hash']=$params['hash'][$j];
                                        $tmp_params['documentdate']=$params['documentdate'][$j];
                                        $tmp_params['documentType']=$params['documentType'][$j];
                                        $tmp_params['tsa']=$params['tsa'][$j];
                                        $tmp_params['tsa_hash']=$params['tsa_hash'][$j];
                                        $tmp_params['ipfs']=$params['ipfs'][$j];
                                        $return_values[$j]=$this->ProcessPOST("UploadDoc",$tmp_params,1);
                                }
                                $return_values = str_replace('"',"'",$return_values);
                                $return_data = json_encode($return_values,TRUE);
                                echo $return_data;
                        break;
                        case 'UploadDocandCreateTSA':
                                $TSAparams['hash']=$params['hash'];
                                $data['TSA']=$this->ProcessPOST('CreateTSA',$TSAparams,1);
                                $data['TSA'] = (array) json_decode($data['TSA'],TRUE);
                                $tsa64 = $data['TSA']['tsa'];
                                $tsa_hash = $data['TSA']['tsa_hash'];
                                
                                $IPFSparams['hash']=$params['hash'];
                                $IPFSparams['Tsa']=$tsa64;
                                $data['IPFS']=$this->ProcessPOST('CreateIPFS',$IPFSparams,1);
                                $data['IPFS'] = (array) json_decode($data['IPFS'],TRUE);
                                //UploadDoc
                                $ipfs_hash = $data['IPFS']['Hash'];
                                $UPparams['hash']=$params['hash'];
                                $UPparams['documentdate']=$params['documentdate'];
                                $UPparams['documentType']=$params['documentType'];
                                $UPparams['tsa']=$tsa_hash;
                                $UPparams['tsa_hash']=$tsa64;
                                $UPparams['ipfs']=$ipfs_hash;
                               
                                
                                $this->ProcessPOST("UploadDoc",$UPparams,0);
                                exit;


                        break;
                        case 'CreateIPFS':
                                $required = $this->RequiredFields('CreateIPFS');
                                if (count($params)<count($required)) {
                                        $this->SendHeader("417");
                                        $data['error']=1;
                                        $data['errormsg']="Required fields are incomplete, received ".count($params);
                                        echo json_encode($data, TRUE);
                                        exit;
                                }else{
                                        foreach($required as $key) {
                                                if ($params[$key]) {
                                                        $tmp = 0;
                                                }else{
                                                        $tmp = 1;
                                                        $this->SendHeader("417");
                                                        $data['error']=1;
                                                        $data['errormsg']=$key." field ??????";
                                                        echo json_encode($data, TRUE);
                                                        exit;
                                                }

                                        }
                                        if ($tmp==0) {
                                                //Esta todo ok, no hay mas que requiredFields
                                                $file_hash = $params['hash'];
                                                $Tsa64 = $params['Tsa'];
                                                if (strlen($file_hash)!=128) {
                                                        $this->SendHeader("417");
                                                        $data['error']=1;
                                                        $data['errormsg']="hash must be sha512";
                                                        echo json_encode($data, TRUE);
                                                        exit;
                                                }
                                                
                                                exec($this->phpPATH." ".$this->createIPFS." ".$file_hash." ".$Tsa64,$result);
                                                if ($return==0) {
                                                        echo $result[0];
                                                }else{
                                                        return $result[0];
                                                }
                                        }
                                }
                        break;
                        case 'CreateTSA':
                                $required = $this->RequiredFields('CreateTSA');
                                if (count($params)<count($required)) {
                                        $this->SendHeader("417");
                                        $data['error']=1;
                                        $data['errormsg']="Required fields are incomplete, received ".count($params);
                                        echo json_encode($data, TRUE);
                                        exit;
                                }else{
                                        foreach($required as $key) {
                                                //$requireFields=Array('hash','documentdate','documentType','tsa');
                                                if ($params[$key]) {
                                                        $tmp = 0;
                                                }else{
                                                        $tmp = 1;
                                                        $this->SendHeader("417");
                                                        $data['error']=1;
                                                        $data['errormsg']=$key." field ??????";
                                                        echo json_encode($data, TRUE);
                                                        exit;
                                                }

                                        }
                                        if ($tmp==0) {
                                                //Esta todo ok, no hay mas que requiredFields
                                                $file_hash = $params['hash'];
                                                if (strlen($file_hash)!=128) {
                                                        $this->SendHeader("417");
                                                        $data['error']=1;
                                                        $data['errormsg']="hash must be sha512";
                                                        echo json_encode($data, TRUE);
                                                        exit;
                                                }
                                                exec($this->createTSA." ".$file_hash,$result);
                                                if ($return==0) {
                                                        echo $result[0];
                                                }else{
                                                        return $result[0];
                                                }
                                        }
                                }
                        break;
                        case 'UploadDoc':
                                
                                $required = $this->RequiredFields("UploadDoc");
                                if (count($params)<count($required)) {
                                        $this->SendHeader("417");
                                        $data['error']=1;
                                        $data['errormsg']="Required fields are incomplete, received ".count($params);
                                        echo json_encode($data, TRUE);
                                }else{
                                        foreach($required as $key) {
                                                //$requireFields=Array('hash','documentdate','documentType','tsa');
                                                if ($params[$key]) {
                                                        $tmp = 0;
                                                }else{
                                                        $tmp = 1;
                                                        $this->SendHeader("417");
                                                                $data['error']=1;
                                                                $data['errormsg']=$key." field ??????";
                                                                echo json_encode($data, TRUE);
                                                        exit;
                                                }

                                        }
                                        if ($tmp==0) {
                                                //Pasamos todo, pero... hay que ver si no mando fruta con los campos extras
                                                $available = $this->availableFields("UploadDoc");
                                                
                                                foreach($params as $key => $val) {
                                                        if (!in_array($key,$available)) {

                                                                $this->SendHeader("417");
                                                                $data['error']=1;
                                                                $data['errormsg']="The field ".$key." does not exists";
                                                                echo json_encode($data, TRUE);
                                                                exit;
                                                        }
                                                }

                                                //Y ahora mirar si los tipos coinciden...
                                                $fieldTypes = $this->FieldTypes("UploadDoc");
                                               
                                                foreach($fieldTypes as $key => $val) {
                                                        
                                                        if ($val=="integer") {
                                                                if (!is_integer($params[$key])) {
                                                                        $this->SendHeader("417");
                                                                        $data['error']=1;
                                                                        $data['errormsg']="The field ".$$key." is not an integer";
                                                                        echo json_encode($data, TRUE);
                                                                        exit;
                                                                }
                                                        }elseif ($val=="string") {
                                                                if (!is_string($params[$key])) {
                                                                        $this->SendHeader("417");
                                                                        $data['error']=1;
                                                                        $data['errormsg']="The field ".$key." is not an string";
                                                                        echo json_encode($data, TRUE);
                                                                        exit;
                                                                }
                                                        }
                                                        
                                                }
                                                //hash -> sha512
                                                $file_hash = $params['hash'];
                                                if (strlen($file_hash)!=128) {
                                                        $this->SendHeader("417");
                                                        $data['error']=1;
                                                        $data['errormsg']="hash must be sha512";
                                                        echo json_encode($data, TRUE);
                                                        exit;
                                                }


                                                $lowerhash = strtolower($params['hash']);
                                                $sql = "SELECT pendingId,processed FROM pendingDocs WHERE lowerhash='".$lowerhash."'";
                                                $res = mysqli_query($this->db, $sql);
                                                $dat = mysqli_fetch_array($res);
                                                $pendingID = $dat[0];
                                                $processed = $dat[1];
                                                if ($pendingID>0) {
                                                        $data['hash']=$params['hash'];
                                                        if ($processed=="no") {
                                                                $data['error']="1";
                                                                $data['errormsg']="The hash ".$params['hash']." is on queued to be processed";
                                                                $header="400";
                                                        }else{
                                                                $data['error']="2";
                                                                $data['errormsg']="The hash ".$params['hash']." is already processed";
                                                                $header="400";
                                                        }
                                                }else{
                                                        $lowerhash = strtolower($params['hash']);
                                                        $sql = "INSERT INTO pendingDocs(hash,documentdate,documentType,tsa,lowerhash,userId,tsahash,ipfshash) values('".$params['hash']."','".$params['documentdate']."','".$params['documentType']."','".$params['tsa']."','".$lowerhash."','".$this->GetUserId($_SESSION['username'])."','".$params['tsa_hash']."','".$params['ipfs']."')";
                                                        $res = mysqli_query($this->db, $sql);
                                                        $data['hash']=$params['hash'];
                                                        $data['tsa']=$params['tsa'];
                                                        $data['tsa_hash']=$params['tsa_hash'];
                                                        $data['ipfs']=$params['ipfs'];
                                                        $data['error']="0";
                                                        $data['owner']=$this->ShowOwnerAddress($_SESSION['username']);
                                                        $data['errormsg']="It is queued to be processed";
                                                        $header="202";
                                                }
                                                $data = json_encode($data, TRUE);
                                                $this->SendHeader($header);
                                                if ($return==0) {
                                                        echo $data;
                                                }else{
                                                        return $data;
                                                }
                                        }
                                }
                        break;
                }
        }
        function ProcessGET($resource,$params) {
                switch ($resource) {
                        case 'GetDoc':
                                if ($params[3]=="") {
                                        $this->SendHeader('417');
                                        exit;
                                }
                                exec($this->node." ".$this->nodefiles."getDoc.js ".$params[3]." 2>&1 &",$result);
                                echo $result[0];
                        break;
                        case 'GetNotValidated':
                                $data['error']="Deprecated";
                                $result = json_encode($data, TRUE);
                                echo $result;
                        break;
                        case 'CheckHistory':
                                if ($params[3]=="") {
                                        $this->SendHeader('406');
                                        exit;
                                }
                                exec($this->node." ".$this->nodefiles."checkHistory.js ".$params[3]." 2>&1 &",$result);
                                echo $result[0];
                        break;
                        
                }
        }
        function SendHeader($statusCode) {
                static $status_codes = null;

                if ($status_codes === null) {
                        $status_codes = array (
                            100 => 'Continue',
                            101 => 'Switching Protocols',
                            102 => 'Processing',
                            200 => 'OK',
                            201 => 'Created',
                            202 => 'Accepted',
                            203 => 'Non-Authoritative Information',
                            204 => 'No Content',
                            205 => 'Reset Content',
                            206 => 'Partial Content',
                            207 => 'Multi-Status',
                            300 => 'Multiple Choices',
                            301 => 'Moved Permanently',
                            302 => 'Found',
                            303 => 'See Other',
                            304 => 'Not Modified',
                            305 => 'Use Proxy',
                            307 => 'Temporary Redirect',
                            400 => 'Bad Request',
                            401 => 'Unauthorized',
                            402 => 'Payment Required',
                            403 => 'Forbidden',
                            404 => 'Not Found',
                            405 => 'Method Not Allowed',
                            406 => 'Not Acceptable',
                            407 => 'Proxy Authentication Required',
                            408 => 'Request Timeout',
                            409 => 'Conflict',
                            410 => 'Gone',
                            411 => 'Length Required',
                            412 => 'Precondition Failed',
                            413 => 'Request Entity Too Large',
                            414 => 'Request-URI Too Long',
                            415 => 'Unsupported Media Type',
                            416 => 'Requested Range Not Satisfiable',
                            417 => 'Expectation Failed',
                            422 => 'Unprocessable Entity',
                            423 => 'Locked',
                            424 => 'Failed Dependency',
                            426 => 'Upgrade Required',
                            500 => 'Internal Server Error',
                            501 => 'Not Implemented',
                            502 => 'Bad Gateway',
                            503 => 'Service Unavailable',
                            504 => 'Gateway Timeout',
                            505 => 'HTTP Version Not Supported',
                            506 => 'Variant Also Negotiates',
                            507 => 'Insufficient Storage',
                            509 => 'Bandwidth Limit Exceeded',
                            510 => 'Not Extended'
                        );
                    }

            if ($status_codes[$statusCode] !== null) {
                $status_string = $statusCode . ' ' . $status_codes[$statusCode];
                header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status_string, true, $statusCode);
            }
        }
}

