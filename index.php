<!DOCTYPE HTML>
<html>
   <head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
      <meta name="Description" content="A tool custom made for ILRI (International Livestock Research Institute) to convert json files recieved from ODK Aggregate to Excel files" />
      <meta name="robots" content="index,follow" />
      <title>ODK Parser</title>
      <link rel="stylesheet" type="text/css" href="css/bootstrap.css"/>
      <script src="/common/jquery/jquery-1.8.3.min.js"></script>
      <script src="js/bootstrap.js"></script>
      <script src="js/parse.js"></script>
   </head>
   <body>
      <form class="form-horizontal">
         <div class="control-group">
            <label class="control-label" for="name">Full Name</label>
            <div class="controls">
               <input type="text" id="name" name="name"/>
            </div>
         </div>
         <div class="control-group">
            <label class="control-label" for="email">Email Address</label>
            <div class="controls">
               <input type="text" id="email" name="email"/>
            </div>
         </div>
         <div class="control-group">
            <label class="control-label" for="file_name">Name the Excel file to be generated</label>
            <div class="controls">
               <input type="text" id="file_name" name="file_name"/>
            </div>
         </div>
         <div class="controls">
            <label class="control-label" for="json_file">JSON File</label>
            <div class="controls">
               <input id="json_file" name="json_file" type="file"/>
            </div>
         </div>
         <div class="controls">
            <label class="control-label" for="xml_file">XML File</label>
            <div class="controls">
               <input id="xml_file" name="xml_file" type="file"/>
            </div>
         </div>
         <div class="controls">
            <button id="generate_b" name="generate_b" onclick="return false;" class="btn-primary">Generate</button>
         </div>
      </form>
      <script>
         $(document).ready( function() {
         $("#generate_b").click(function ()
            {
               var parser = new Parse();
            });
         });
		   
      </script>
   </body>
</html>
