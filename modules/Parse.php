<?php
error_reporting(E_ALL);

class Parser {
   private $TAG = "Parse.php";
   private $ROOT = "../";
   private $logHandler;
   private $settings;
   private $phpExcel;
   private $jsonObject;
   private $sheetIndexes;
   private $allColumnNames;
   private $nextRowName;
   private $imagesDir;
   private $downloadDir;
   private $sessionID;
   private $xmlString;
   private $xmlValues;
   private $rootDirURI;
   
   public function __construct() {
      //load settings
      $this->loadSettings();
      
      //include modules
      include_once $this->ROOT.'modules/Log.php';
      $this->logHandler = new LogHandler();
      
      include_once $this->ROOT.'modules/PHPExcel.php';
      $this->phpExcel = new PHPExcel();
      $this->setExcelMetaData();
      
      //init other vars
      $this->sheetIndexes = array();
      $this->allColumnNames = array();
      $this->nextRowName = array();
      $this->sessionID = session_id();
      if($this->sessionID == NULL || $this->sessionID == "") {
         $this->sessionID = round(microtime(true) * 1000);
      }
      $this->imagesDir = $this->ROOT.'download/'.$this->sessionID.'/images';
      $this->downloadDir = $this->ROOT.'download/'.$this->sessionID;
      $this->xmlValues = array();
      
      $this->rootDirURI = "/~jason/ilri/ODKParser/";
      
      //parse json String
      $this->parseJson();
      $this->loadXML();
      
      //process all responses
      $mainSheetKey = "main_sheet";
      
      foreach($this->jsonObject as $currentJsonObject) {
         $this->createSheetRow($currentJsonObject, $mainSheetKey);
      }
      
      //save the excel object
      if(!file_exists($this->downloadDir)){
         mkdir($this->downloadDir,0777,true);
      }
      $objWriter = new PHPExcel_Writer_Excel2007($this->phpExcel);
      $objWriter->save($this->downloadDir.'/'.$_POST['fileName'].'.xlsx');
      
      //zip parsed files
      $zipName = 'download/'.$this->sessionID.'.zip';
      $this->zipParsedItems($this->downloadDir, $this->ROOT.$zipName);
      $this->deleteDir($this->downloadDir);
      
      //send zip file to specified email
      $this->sendZipURL($zipName);
   }
   
   private function loadSettings() {
      $settingsDir = $this->ROOT."config/";
      if(file_exists($settingsDir."main.ini")) {
         $this->settings = parse_ini_file($settingsDir."main.ini");
      }
   }
   
   private function setExcelMetaData() {
      $this->phpExcel->getProperties()->setCreator($_POST['creator']);
      $this->phpExcel->getProperties()->setLastModifiedBy($_POST['creator']);
      $this->phpExcel->getProperties()->setTitle($_POST['fileName']);
      $this->phpExcel->getProperties()->setSubject("Created using ODK Parser");
      $this->phpExcel->getProperties()->setDescription("This Excel file has been generated using ODK Parser that utilizes the PHPExcel library on PHP. ODK Parse was created by Jason Rogena (j.rogena@cgiar.org)");
   }
   
   private function getColumnName($parentKey, $key){//a maximum of 676 (26*26) columns
      $columnNames = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");
      $indexOfKey = array_search($key, $this->allColumnNames[$parentKey]);
      $x = intval($indexOfKey/26) -1;
      $y = fmod($indexOfKey, 26);
      $columnName = "";
      if($x>=0){
         $columnName = $columnNames[$x];
      }
      if($y<26){
         $columnName = $columnName.$columnNames[$y];
         echo 'returned from getcolumn name '.$columnName.'<br/>';
         return $columnName;
      }
      
   }
   
   private function createSheetRow($jsonObject, $parentKey, $parentCellName = NULL) {
      //check if sheet for parent key exists
      $sheetArrayKeys = array_keys($this->sheetIndexes);
      if(!in_array($parentKey, $sheetArrayKeys)) {
         echo 'sheet for '.$parentKey.' does not exist<br/>';
         //create sheet for parent key
         echo 'size of sheet indexes before '.sizeof($this->sheetIndexes)."<br/>";
         $this->sheetIndexes[$parentKey] = sizeof($this->sheetIndexes);
         echo 'size of sheet indexes now '.sizeof($this->sheetIndexes)."<br/>";
         $this->nextRowName[$parentKey] = 1;
         $this->allColumnNames[$parentKey] = array();
         
         if(sizeof($this->sheetIndexes)>1){
            $this->phpExcel->createSheet();
            echo 'this is not the first sheet, therefore calling createSheet<br/>';
         }
         $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentKey]);
         echo 'set active sheet index to '.$this->sheetIndexes[$parentKey]."<br/>";
         $this->phpExcel->getActiveSheet()->setTitle($parentKey);
      }
      else {
         //set active sheet to that which corresponds to parent key
         $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentKey]);
         echo 'sheet for '.$parentKey.' already exists<br/>';
      }
      
      //split keys and values in jsonObject
      echo 'splitting keys and values in jsonObject<br/>';
      $keys = array_keys($jsonObject);
      $values = array();
      $index = 0;
      foreach($jsonObject as $value) {
         $values[$index] = $value;
         $index++;
      }
      echo 'size of values is '.sizeof($values)."<br/>";
      
      //get next row name for parent key
      $rowName = $this->nextRowName[$parentKey];
      echo 'row name is '.$rowName.'<br/>';
      
      //set name of parent cell as first cell in row if is set
      if ($parentCellName != NULL) {
         if (!in_array($this->allColumnNames[$parentKey], "Parent_Cell")) {
            echo 'pushing Parent_Cell to allColumnNames array for ' . $parentKey . '<br/>';
            array_push($this->allColumnNames[$parentKey], "Parent_Cell");
            //$this->allColumnNames[$parentKey][sizeof($this->allColumnNames[$parentKey])]="Parent_Cell";
         }
         $columnName = $this->getColumnName($parentKey, "Parent_Cell");
         if ($columnName != FALSE) {
            $cellName = $columnName . $rowName;
            $this->phpExcel->getActiveSheet()->setCellValue($cellName, $parentCellName);
            $this->phpExcel->getActiveSheet()->getColumnDimension($columnName)->setAutoSize(true);
            //$this->phpExcel->getActiveSheet()->getStyle($cellName)->getAlignment()->setWrapText(true);
         } else {
            echo 'column name for Parent_Cell not found<br/>';
            print_r($this->allColumnNames[$parentCellName]);
         }
      }
      
      //add all keys and respective values to row
      if(sizeof($keys) == sizeof($values) && sizeof($value) > 0) {
      
         echo 'adding columns from here<br/>';
         for($index = 0; $index < sizeof($keys); $index++) {
            //add key to allColumns array
            $columnExisted = TRUE;
            if(!in_array($this->allColumnNames[$parentKey], $keys[$index])) {
               $columnExisted = FALSE;
               echo 'pushing '.$keys[$index].' to allColumnNames array for '.$parentKey.'<br/>';
               //array_push($this->allColumnNames[$parentKey], $keys[$index]);
               $this->allColumnNames[$parentKey][sizeof($this->allColumnNames[$parentKey])]=$keys[$index];
               
            }
            
            $columnName = $this->getColumnName($parentKey, $keys[$index]);
            if($columnExisted == FALSE) {
               $this->phpExcel->getActiveSheet()->getColumnDimension($columnName)->setAutoSize(true);
            }
            
            if ($columnName != FALSE) {
               $cellName = $columnName . $rowName;
               $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentKey]);

               if (!is_array($values[$index])) {
                  echo 'value of '.$keys[$index].' is '.$values[$index].'<br/>';
                  
                  if(filter_var($values[$index], FILTER_VALIDATE_URL)) {
                     $values[$index] = $this->downloadImage($values[$index]);
                  }
                  
                  if($values[$index]== "") {
                     $values[$index] = "NULL";
                  }
                  $this->phpExcel->getActiveSheet()->setCellValue($cellName, $this->convertKeyToValue($values[$index]));
               } 
               else {//if values is an array
                  if (sizeof($values[$index] > 0)) {
                     echo 'value of '.$keys[$index].' is an array<br/>';
                     $this->phpExcel->getActiveSheet()->setCellValue($cellName, "Check " . $keys[$index] . " sheet");
                     foreach ($values[$index] as $childJsonObject) {
                        $this->createSheetRow($childJsonObject, $keys[$index], $parentKey." (".$cellName.")");
                     }
                  }
                  else {
                     echo 'value of '.$keys[$index].' is an array but is empty<br/>';
                     $this->phpExcel->getActiveSheet()->setCellValue($cellName, "NULL");
                  }
               }
               //$this->phpExcel->getActiveSheet()->getStyle($cellName)->getAlignment()->setWrapText(true);
            }
            else {
               echo 'column name for '.$keys[$index].' not found<br/>';
               print_r($this->allColumnNames[$parentKey]);
            }
            
         }
      }
      else {//probably means the jsonObject is just a string
         echo 'adding row with only one column since json object is just string<br/>';
         $columnExisted = TRUE;
         if(!in_array($this->allColumnNames[$parentKey], "values")) {
            $columnExisted = FALSE;
            echo 'pushing values to allColumnNames array for ' . $parentKey . '<br/>';
            //array_push($this->allColumnNames[$parentKey], $keys[$index]);
            $this->allColumnNames[$parentKey][sizeof($this->allColumnNames[$parentKey])] = 'values';
         }

         $columnName = $this->getColumnName($parentKey, 'values');
         if ($columnExisted == FALSE) {
            $this->phpExcel->getActiveSheet()->getColumnDimension($columnName)->setAutoSize(true);
         }
         
         if ($columnName != FALSE) {
            
            if(filter_var($jsonObject, FILTER_VALIDATE_URL)) {
               $jsonObject = $this->downloadImage($jsonObject);
            }
            
            if($jsonObject == "") {
               $jsonObject = "NULL";
            }
            
            $cellName = $columnName . $rowName;
            $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentKey]);
            $this->phpExcel->getActiveSheet()->setCellValue($cellName, $this->convertKeyToValue($jsonObject));
         }
      }
      $this->nextRowName[$parentKey]++;
   }
   
   private function downloadImage($url) {
      $contentType = get_headers($url, 1)["Content-Type"];
      echo 'content type is '.$contentType."<br/>";
      if(strpos($contentType, 'image')!==NULL) {
         if(!file_exists($this->imagesDir)) {
            mkdir($this->imagesDir,0777,true);
         }
         echo 'starting downloads'.$this->sessionID.'<br/>';
         $timestamp = round(microtime(true) * 1000);
         $name = $timestamp.".".str_replace("image/", "", $contentType);
         $img = $this->imagesDir.'/'.$name;
         file_put_contents($img, file_get_contents($url));
         return $name;
      }
      else {
         return $url;
      }
   }
   
   function zipParsedItems($source, $destination, $include_dir = false) {

      if (!extension_loaded('zip') || !file_exists($source)) {
         return false;
      }

      if (file_exists($destination)) {
         unlink($destination);
      }

      $zip = new ZipArchive();
      if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
         return false;
      }
      $source = str_replace('\\', '/', realpath($source));

      if (is_dir($source) === true) {

         $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

         if ($include_dir) {

            $arr = explode("/", $source);
            $maindir = $arr[count($arr) - 1];

            $source = "";
            for ($i = 0; $i < count($arr) - 1; $i++) {
               $source .= '/' . $arr[$i];
            }

            $source = substr($source, 1);

            $zip->addEmptyDir($maindir);
         }

         foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..')))
               continue;

            $file = realpath($file);

            if (is_dir($file) === true) {
               $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            } else if (is_file($file) === true) {
               $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
         }
      } else if (is_file($source) === true) {
         $zip->addFromString(basename($source), file_get_contents($source));
      }

      return $zip->close();
   }
   
   public function deleteDir($dirPath) {
      if (!is_dir($dirPath)) {
         throw new InvalidArgumentException("$dirPath must be a directory");
      }
      if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
         $dirPath .= '/';
      }
      $files = glob($dirPath . '*', GLOB_MARK);
      foreach ($files as $file) {
         if (is_dir($file)) {
            self::deleteDir($file);
         } else {
            unlink($file);
         }
      }
      rmdir($dirPath);
   }
   
   private function convertKeyToValue($key) {
      if(array_key_exists($key, $this->xmlValues)) {
         return $this->xmlValues[$key];
      }
      else {
         return $key;
      }
   }
   
   private function parseJson() {
      $jsonString = $_POST['jsonString'];
      $this->jsonObject = json_decode($jsonString, TRUE);
      print_r($this->jsonObject);
   }
   
   private function loadXML() {
      //$this->xmlString = file_get_contents($this->ROOT . "animals.xml");
      $this->xmlString = $_POST['xmlString'];
      $subStrings = array();
      $count = 0;
      while (1 == 1) {
         $pref = strpos($this->xmlString, "<text");
         $suf = strpos($this->xmlString, "</text>") + 7;
         if ($pref !== FALSE && $suf !== FALSE) {
            $subStrings[$count] = substr($this->xmlString, $pref, ($suf - $pref));
            $this->xmlString = substr_replace($this->xmlString, "", $pref, ($suf - $pref));
            //get the id
            $idPref = strpos($subStrings[$count], "id=");
            $idSuf = strpos($subStrings[$count], "><value>");
            $id = substr($subStrings[$count], $idPref + 4, ($idSuf - $idPref) - 5);

            //get the value
            $valuePref = strpos($subStrings[$count], "<value>");
            $valueSuf = strpos($subStrings[$count], "</value>");
            $value = substr($subStrings[$count], $valuePref + 7, ($valueSuf - $valuePref) - 7);

            $this->xmlValues[$id] = $value;
            $count++;
         } 
         else {
            break;
         }
      }
      print_r($this->xmlValues);
   }
   
   private function sendZipURL($zipName) {
      $url = "http://".$_SERVER['HTTP_HOST'].$this->rootDirURI.$zipName;
      $emailSubject = "ODK Parser finished generating ".$_POST['fileName'];
      $message = "Hi ".$_POST['creator'].",\nODK Parser has finished generating ".$_POST['fileName'].".xlsx. You can download the file along with its companion images as a zip file from the following link ".$url." . This is an auto-generated email, please do not reply to it.";
      //$headers = "From: noreply@cgiar.org";
      //mail($_POST['email'], $emailSubject, $message, $headers);
      
      shell_exec('echo "'.$message.'"|'.$this->settings['mutt_bin'].' -F '.$this->settings['mutt_config'].' -s "'.$emailSubject.'" -- '.$_POST['email']);
   }
}

$obj = new Parser();
?>
