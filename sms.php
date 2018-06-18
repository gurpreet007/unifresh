<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href=
      "https://maxcdn.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css">
    <script src=
      "https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js">
    </script>
    <script src=
    "https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js">    </script>
    <script src=
      "https://maxcdn.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js">
    </script> 
  </head>
  <body>
<?php
  function getAuthHTTPHeader($method, $action) {
    $ts = date_timestamp_get(date_create());
    $nonce = bin2hex(random_bytes(10));
    $http_host = "api.smsglobal.com";
    $http_port = "443";
    $opt_data = "";

    $key = "955ef5eeb4e85dc312ced7193e4cea67";
    $secret = "93b20c19134c8efedcc6504f369b1c21";

    $concat_str = sprintf("%s\n%s\n%s\n%s\n%s\n%s\n%s\n",
      $ts, $nonce, $method, $action, $http_host, $http_port, $opt_data);

    $sig = hash_hmac("sha256", $concat_str, $secret, true);

    $hash = base64_encode($sig);

    $mac = sprintf('MAC id="%s", ts="%s", nonce="%s", mac="%s"', 
      $key, $ts, $nonce, $hash);

    return $mac;
  }

  function flash($msg, $type="alert-success") {
    $strongStr = "Info!";
    switch($type) {
      case "alert-success":
        $strongStr = "Success!";
        break;
      case "alert-danger":
        $strongStr = "Error!";
        break;
    }
    echo "<div class='alert $type'> <strong>$strongStr</strong> $msg </div>";
  }

  function sendMessage($msg) {
    $action = "/v2/sms/";
    $crl = curl_init("https://api.smsglobal.com".$action);
    $header = [ 'Content-type: application/json',
                'Accept: application/json',
                'Authorization: '. getAuthHTTPHeader("POST", $action)];
    curl_setopt($crl, CURLOPT_HTTPHEADER, $header);

    $uniqMobNums = array_values(array_unique($_SESSION["selCusts"])); 
    $data = [ 
              'destinations'  => $uniqMobNums,
              'origin'        => 'test', 
              'message'       => $msg,
              'sharedPool'    =>  '',
            ];
    curl_setopt($crl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($crl, CURLOPT_POST, true);
    curl_setopt($crl, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($crl, CURLOPT_RETURNTRANSFER, true);
    $rest = curl_exec($crl) or die(curl_error($crl));
    curl_close($crl);
    if(strpos($rest, "authentication failed")) 
      flash($rest,"alert-danger");
    else 
      flash(count($uniqMobNums)." Messages Sent","alert-success");
  }

  function dbConnect() {
    $dbname = 'kunwardb.fdb';
    $dbuser = 'sysdba';
    $dbpass = 'masterkey';

    $dbh = ibase_connect($dbname, $dbuser, $dbpass) or 
      die(ibase_errmsg());
    return $dbh;
  }

  function dbClose($dbh) {
    //release the handle associated with the connection
    ibase_close($dbh);
  }

  function fillAllCustomers(){
    $dbh = dbConnect();
    $sql = 
      "select customer, customermobile from customermaster order by customer";

    $result = ibase_query($dbh, $sql) or die (ibase_errmsg());
    while($row = ibase_fetch_object($result)) {
      echo sprintf("<option value='%s|%s' %s>%s</option>",
        $row->CUSTOMERMOBILE, $row->CUSTOMER,
        ($_POST["allCustomers"]==$row->CUSTOMER)?"selected='selected'":"", 
        $row->CUSTOMER);
    }
    ibase_free_result($result);

    dbClose($dbh); 
  }
  
  function saveTemp($tname, $tmsg) {
    $tmsg = str_replace("'", "", $tmsg);
    $tname = str_replace("'", "", $tname);
    if(is_null($tmsg) || is_null($tname) || empty($tmsg) || empty($tname)) {
      flash("Invalid message or template name", "alert-danger");
      return;
    }
    $dbh = dbConnect();
    $sql = sprintf("update or insert into templates (id, name, message) ".
            "values((select coalesce(max(id),0)+1 from templates),".
            "'%s','%s') matching(name)",$tname, $tmsg);
    $result = ibase_query($dbh, $sql) or die (ibase_errmsg());
    dbClose($dbh);   
    flash("Template Saved","alert-success");
  } 
  
  function showTemps() {
    $dbh = dbConnect();
    $sql = "select id, name from templates order by name";

    $result = ibase_query($dbh, $sql) or die (ibase_errmsg());
    while($row = ibase_fetch_object($result)) {
      echo sprintf("<option value='%s' %s>%s</option>", 
        $row->ID, 
        ($_POST["selTemplate"]==$row->ID)?"selected='selected'":"", 
        $row->NAME);
    }
    ibase_free_result($result);

    dbClose($dbh); 
  }

  function useTemp($Id) {
    $dbh = dbConnect();
    $sql = "select message from templates where id=".$Id;
    $result = ibase_query($dbh, $sql) or die (ibase_errmsg());
    $_POST["smsContent"] = stripslashes(ibase_fetch_assoc($result)["MESSAGE"]);
    ibase_free_result($result);
    dbClose($dbh);
  }

  function fetchNoOrderCusts() {
    $selCusts = [];
    $today = date("Y-m-d");
    $dbh = dbConnect();
    $sql = sprintf("select distinct cm.customer, cm.customermobile ".
      "from customermaster cm left outer join salesheader sh ".
      "on cm.customer = sh.customer where sh.customer not in ".
      "(select customer from salesheader where orderdate='%s') ".
      "order by cm.customer", $today);
    $result = ibase_query($dbh, $sql) or die(ibase_errmsg());
    while($row = ibase_fetch_object($result)) {
      $selCusts[$row->CUSTOMER] = $row->CUSTOMERMOBILE;
    }
    ibase_free_result($result);
    dbClose($dbh);
    $_SESSION["selCusts"] = $selCusts;
  }

  function showCusts() {
    ksort($_SESSION["selCusts"]);
    foreach ($_SESSION["selCusts"] as $cust=>$mobile){
      echo sprintf("<option value='%s|%s'>%s</option>", $mobile, $cust, $cust);
    }
  }

  function delCust($Id) {
    $cust = explode("|", $Id, 2)[1];
    unset($_SESSION["selCusts"]["$cust"]);
    flash("Customer Deleted","alert-success");
  }

  function addCust($Id) {
    $mob = explode("|", $Id,2)[0];
    $cust = explode("|",$Id,2)[1];
    $_SESSION["selCusts"][$cust] = $mob;
    flash("Customer Added to Selected Customers List","alert-success");
  }

  if($_SERVER["REQUEST_METHOD"] == "POST") {
    switch($_POST["btnSubmit"]) {
      case "saveTemp":
        saveTemp($_POST["newTemplateName"], 
                 $_POST["smsContent"]);
        break; 
      case "useTemp":
        useTemp(addslashes($_POST["selTemplate"]));
        break;
      case "delCust":
        delCust(addslashes($_POST["selCustomers"]));
        break;
      case "addCust":
        addCust(addslashes($_POST["allCustomers"]));
        break;
      case "sendMsg":
        sendMessage($_POST["smsContent"]);
        break;
    }
  }
  else {
    fetchNoOrderCusts();
  }
?>
    <div class="container">
      <div class="page-header">
        <h1>Unifresh SMS Sender</h1>
      </div>
      <form method="post" 
          action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) ?>">
        
        <div class="form-group">
          <label for="allCustomers">All Customers</label>
          <select class="form-control form-control-sm" 
              name="allCustomers" id="allCustomers">
            <?php fillAllCustomers();  ?>
          </select>
          <small id="selCustomersHelp" class="form-text text-muted">
            Select customers to add to the list below.
          </small>
          <button type="submit" class="btn btn-primary" name="btnSubmit"
            value="addCust" id="btnAdd">Add Selected Customer</button>
        </div>

        <div class="form-group">
          <label for="selCustomers">Selected Customers</label>
          <select class="form-control form-control-sm" 
              name="selCustomers" id="selCustomers">
            <?php showCusts(); ?>
          </select>
          <small id="selCustomersHelp" class="form-text text-muted">
            This list is pre-filled with customers who have not 
              placed their order for today (<?php echo date("d-m-Y"); ?>).
          </small>
          <button type="submit" class="btn btn-danger" name="btnSubmit"
            value="delCust" id="btnDel">Delete Selected Customer</button>
        </div>

        <div class="form-group">
          <label for="selTemplate">Select SMS Template</label>
          <select class="form-control form-control-sm" 
            id="selTemplate" name="selTemplate">
            <?php showTemps();  ?>
          </select>
          <button type="submit" class="btn btn-primary" name="btnSubmit"
            value="useTemp" id="btnDel">Use this template</button>
        </div>

        <div class="form-group">
          <label for="smsContent">Message Body</label>
          <textarea class="form-control" id="smsContent" name="smsContent" 
            rows=2><?php echo $_POST["smsContent"]; ?></textarea>
          <button type="submit" class="btn btn-success" name="btnSubmit" 
            value="sendMsg" id="sendSMS">Send Message</button>
        </div>

        <div class="form-group">
          <input type="text" class="form-control form-control-sm" 
            id="newTemplateName" name="newTemplateName" 
            placeholder="New Template Name" 
            value="<?php echo $_POST["newTemplateName"];  ?>">
          <small id="saveTemplateHelp" class="form-text text-muted">
            You can also enter existing template's name to update its message.
          </small>
          <button type="submit" class="btn btn-primary" name="btnSubmit" 
            value="saveTemp" id="addNewTemplate">
              Save this sms content as a new template
          </button>
        </div>
      </form>
    </div>
  </body>
</html>
