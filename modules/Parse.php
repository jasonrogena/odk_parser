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
      
      //parse json String
      $this->parseJson();
      
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
      $objWriter->save($this->downloadDir.'/parsed.xlsx');
      
      //zip parsed files
      $this->zipParsedItems($this->downloadDir, $this->ROOT.'download'.'/'.$this->sessionID.'.zip');
      $this->deleteDir($this->downloadDir);
   }
   
   private function loadSettings() {
      $settingsDir = $this->ROOT."config/";
      if(file_exists($settingsDir."main.ini")) {
         $this->settings = parse_ini_file($settingsDir."main.ini");
      }
   }
   
   private function setExcelMetaData() {
      //TODO: get metadata from POST
      $this->phpExcel->getProperties()->setCreator("Jason Rogena");
      $this->phpExcel->getProperties()->setLastModifiedBy("Jason Rogena");
      $this->phpExcel->getProperties()->setTitle("Test");
      $this->phpExcel->getProperties()->setSubject("Testing");
      $this->phpExcel->getProperties()->setDescription("Test by Jason Rogena");
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
                  //TODO: download image if is url
                  if(filter_var($values[$index], FILTER_VALIDATE_URL)) {
                     $values[$index] = $this->downloadImage($values[$index]);
                  }
                  
                  if($values[$index]== "") {
                     $values[$index] = "NULL";
                  }
                  $this->phpExcel->getActiveSheet()->setCellValue($cellName, $values[$index]);
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
                     $this->phpExcel->getActiveSheet()->setCellValue($cellName, "");
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
            //TODO: download image if is url
            if(filter_var($jsonObject, FILTER_VALIDATE_URL)) {
               $jsonObject = $this->downloadImage($jsonObject);
            }
            
            if($jsonObject == "") {
               $jsonObject = "NULL";
            }
            
            $cellName = $columnName . $rowName;
            $this->phpExcel->setActiveSheetIndex($this->sheetIndexes[$parentKey]);
            $this->phpExcel->getActiveSheet()->setCellValue($cellName, $jsonObject);
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
   
   private function parseJson() {
      $jsonString = '[{"start_time":"2013-09-18T17:15:12.000+03","DeviceID":"356262054210980","user_name":"collector","ltdc_id":"collector","qr":"http://wac.450f.edgecastcdn.net/80450F/comicsalliance.com/files/2012/09/ron-wimberly---prince-of-cats---09.png","site":null,"cluster":null,"hh_id":null,"hh_head":null,"info_prov":"Thomas","cycle_no":12,"gps:Latitude":-1.2692566667,"gps:Longitude":36.7220366667,"gps:Altitude":1889.8000488281,"gps:Accuracy":4.4000000000,"ee":[{"id":"Test","aq_disp":"acq","dt":"2013-07-18","count":2,"md":"aq_pur","re":"aqr_imp","pr":25,"spr":null,"md":null,"re":null,"pr":null,"bpr":null,"dt_c":null,"dt_other":null,"hm_count":0,"hm":"yes"},{"id":"That","aq_disp":"acq","dt":"2013-06-18","count":6,"md":"aq_gft","re":"aqr_imp","pr":47000,"spr":null,"md":null,"re":null,"pr":null,"bpr":null,"dt_c":null,"dt_other":null,"hm_count":null,"hm":"no"}],"dl":[{"c_id":"Btr","c_dt":"2013-09-18","c_tc":"tc_pm","c_ec":"ec_hp","c_bd":"2013-08-18","c_nm":null,"c_br":["bn_jr","bn_ex.ot","bn_an"],"c_sx":"sex_m","c_wc":"mw_uncon","c_fm":"mf_bf","c_st":"cs_al","c_iu":"iu_rh","c_def":"def_bl","c_dp":null,"dt":null,"re":null,"s_nm":"Gem","s_bd":"2013-09-18","s_br":["bn_ay","bn_sh","bn_ng"],"d_nm":"Him","d_bd":"2012-08-18","d_br":["bn_ay","bn_sh","bn_ex.ot","bn_ng"]}],"bs":[{"id":"Hek","st":"ail","st_r":["rsc_cl"],"ex_dt":"2013-05-18","bn":"That","si":"Red","ss":"ss_av","bo":"Neighbor","cs":20000,"cb":"15000","is_sc":"yes","sc_r":"scr_uet"}],"hr":[{"id":"Join","t_dt":"2013-08-18","dis":"dc_ms","dr_c":11000,"pr_c":12000,"sp":"sp_cahw","tt":"tt_ht","c_st":"psc_rec"}],"ph":[{"id":"Bus","ac":"ac_vc","e_dt":"2013-08-18","c_st":"psc_dd","dr_c":12000,"pr_c":15000,"sp":"sp_wpa","cm":"Something"}],"ms":[{"by":"Buttress","wk":12,"sm":36,"pr_l":21,"is_bc":"yes","bc_r":"rbc_pp"}]},{"start_time":"2013-09-18T17:15:12.000+03","DeviceID":"356262054210980","user_name":"collector","ltdc_id":"collector","qr":"http://en.m.wikipedia.org","site":null,"cluster":null,"hh_id":null,"hh_head":null,"info_prov":"Thomas","cycle_no":12,"gps:Latitude":-1.2692566667,"gps:Longitude":36.7220366667,"gps:Altitude":1889.8000488281,"gps:Accuracy":4.4000000000,"ee":[{"id":"Test","aq_disp":"acq","dt":"2013-07-18","count":2,"md":"aq_pur","re":"aqr_imp","pr":25,"spr":null,"md":null,"re":null,"pr":null,"bpr":null,"dt_c":null,"dt_other":null,"hm_count":0,"hm":"yes"},{"id":"That","aq_disp":"acq","dt":"2013-06-18","count":6,"md":"aq_gft","re":"aqr_imp","pr":47000,"spr":null,"md":null,"re":null,"pr":null,"bpr":null,"dt_c":null,"dt_other":null,"hm_count":null,"hm":"no"}],"dl":[{"c_id":"Btr","c_dt":"2013-09-18","c_tc":"tc_pm","c_ec":"ec_hp","c_bd":"2013-08-18","c_nm":null,"c_br":["bn_jr","bn_ex.ot","bn_an"],"c_sx":"sex_m","c_wc":"mw_uncon","c_fm":"mf_bf","c_st":"cs_al","c_iu":"iu_rh","c_def":"def_bl","c_dp":null,"dt":null,"re":null,"s_nm":"Gem","s_bd":"2013-09-18","s_br":["bn_ay","bn_sh","bn_ng"],"d_nm":"Him","d_bd":"2012-08-18","d_br":["bn_ay","bn_sh","bn_ex.ot","bn_ng"]}],"bs":[{"id":"Hek","st":"ail","st_r":["rsc_cl"],"ex_dt":"2013-05-18","bn":"That","si":"Red","ss":"ss_av","bo":"Neighbor","cs":20000,"cb":"15000","is_sc":"yes","sc_r":"scr_uet"}],"hr":[{"id":"Join","t_dt":"2013-08-18","dis":"dc_ms","dr_c":11000,"pr_c":12000,"sp":"sp_cahw","tt":"tt_ht","c_st":"psc_rec"}],"ph":[{"id":"Bus","ac":"ac_vc","e_dt":"2013-08-18","c_st":"psc_dd","dr_c":12000,"pr_c":15000,"sp":"sp_wpa","cm":"Something"}],"ms":[{"by":"Buttress","wk":12,"sm":36,"pr_l":21,"is_bc":"yes","bc_r":"rbc_pp"}]}]'; //TODO: get jsonString from post
      $this->jsonObject = json_decode($jsonString, TRUE);
   }
}

$obj = new Parser();
?>
