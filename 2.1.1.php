<?php
require_once("./conn_details.php");
require_once("../universal_db_connection.php");

require_once("../utils.php");
require_once("dh.php");

$script_result = "none";
$body = "";
$session = "";
$enc_key = "";
$enc_iv  = "";

function end_script($echo) //only acceptable way to end this script
{
  die($echo);
}
function check_session() //do not check session in version_check, init_session, init_owner_key and check_owner_key as these happen before session is created or version check is done
{
  global $session, $enc_key, $enc_iv, $script_result;

  $result = db_query('SELECT `version_check_done`, `revoked` FROM `sessions` WHERE `session`=?', [$session]);
  $row = $result->fetch_assoc();
  if ($row["version_check_done"] == false || $row["revoked"] == true)
  {
    $script_result = "session check failed";
    end_script("0");
  }
}
function get_data_from_post()
{
  global $body, $session, $enc_key, $enc_iv, $script_result;

  //POST is json encoded
  $data = json_decode(file_get_contents("php://input"), true);  
  if (empty($data["body"]) || empty($data["session"]))
  {
    $script_result = "post data decode failed";
    end_script("0");
  }
  $body = $data["body"];
  $session = $data["session"];

  //get session enc key and iv
  $result = db_query('SELECT `enc_key`, `enc_iv` FROM `sessions` WHERE `session`=?', [$session]);
  if ($result->num_rows == 0)
  {
    $script_result = "session enc data not found";
    end_script("0");
  }
  $row = $result->fetch_assoc();
  $enc_key = $row["enc_key"];
  $enc_iv = $row["enc_iv"];
}
function decrypt_body()
{
  global $body, $enc_key, $enc_iv;

  $body = DH::dec($body, $enc_key, $enc_iv);
  return json_decode($body, true);
}
function check_request_id_exists($request_id)
{
  global $session, $script_result;

  $result = db_query('SELECT COUNT(*) FROM `requests` WHERE `session_id`=(SELECT `id` FROM `sessions` WHERE `session`=?) AND `request_id`=?', [$session, $request_id]);
  if (intval($result->fetch_row()[0]) > 0) { $script_result = "request already used for this session"; end_script("0"); }
  $result = db_query('INSERT INTO `requests` (`session_id`, `request_id`) VALUES ((SELECT `id` FROM `sessions` WHERE `session`=?), ?)', [$session, $request_id]);
}
function get_owner_key_id($owner_key)
{
  global $script_result;

  $result = db_query('SELECT `id` FROM `owners` WHERE `key`=?', [$owner_key]);
  if ($result->num_rows == 0) { $script_result = "owner key not found"; end_script("0"); }
  $row = $result->fetch_assoc();
  return $row["id"];
}

if (isset($_GET["get_one"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || !isset($data["request_id"]) || !isset($data["key"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $request_id = $data["request_id"];
  $key = $data["key"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  $result = db_query('SELECT *, UNIX_TIMESTAMP(`creation_date`) AS `creation_date` FROM `notes` WHERE `key`=? AND `owner_key_id`=? LIMIT 1', [$key, $owner_key_id]);

  $note = $result->fetch_assoc();
  unset($note["id"]);
  unset($note["owner_key_id"]);
  $script_result = "success";
  end_script(DH::enc(json_encode($note), $enc_key, $enc_iv));
}
else if (isset($_GET["get_all"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  $result = db_query('SELECT *, UNIX_TIMESTAMP(`creation_date`) AS `creation_date` FROM `notes` WHERE `owner_key_id`=?', [$owner_key_id]);

  $notes = array();
  while ($row = $result->fetch_assoc())
  {
    unset($row["id"]);
    unset($row["owner_key_id"]);
    $notes[] = $row;
  }
  $script_result = "success";
  end_script(DH::enc(json_encode($notes), $enc_key, $enc_iv));
}
else if (isset($_GET["edit"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["key"]) || !isset($data["title"]) || !isset($data["content"]) || !isset($data["old_title"]) || !isset($data["old_content"]) || !isset($data["overwrite"]) || !isset($data["last_note_key"]) || empty($data["ownerKey"]) || !isset($data["request_id"]) || !isset($data["blur"]) || !isset($data["password"])) { $script_result = "missing data field"; end_script("0"); }
  $key = $data["key"];
  $title = $data["title"];
  $content = $data["content"];
  $old_title = $data["old_title"];
  $old_content = $data["old_content"];
  $overwrite = $data["overwrite"];
  $last_note_key = $data["last_note_key"] == "null" ? null : $data["last_note_key"];
  $owner_key = $data["ownerKey"];
  $request_id = $data["request_id"];
  $blur = $data["blur"];
  $password = $data["password"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //check if this row exists
  $result = db_query('SELECT COUNT(*) FROM `notes` WHERE `key`=? AND `owner_key_id`=?', [$key, $owner_key_id]);
  $exists = intval($result->fetch_row()[0]) > 0;

  //if exists, check for mismatch and possibly update
  if ($exists)
  {
    //should we overwrite if mismatch
    if (!$overwrite)
    {
      //check mismatch
      $result = db_query('SELECT `title`, `content` FROM `notes` WHERE `key`=? AND `owner_key_id`=?', [$key, $owner_key_id]);
      $row = $result->fetch_assoc();
      if ($row["title"] != $old_title || $row["content"] != $old_content) { $script_result = "success"; end_script(DH::enc("mismatch", $enc_key, $enc_iv)); }
    }

    //update
    db_query('UPDATE `notes` SET `title`=?, `content`=?, `blur`=?, `password`=? WHERE `key`=? AND `owner_key_id`=?', [$title, $content, $blur, $password, $key, $owner_key_id]);
  }
  //if not, insert
  else
  {
    db_query('INSERT INTO `notes` (`key`, `title`, `content`, `owner_key_id`, `blur`, `password`, `last_note_key`) VALUES (?, ?, ?, ?, ?, ?, ?)', [$key, $title, $content, $owner_key_id, $blur, $password, $last_note_key]);
  }

  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["delete"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || empty($data["key"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $key = $data["key"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  db_query('DELETE FROM `notes` WHERE `key`=? AND `owner_key_id`=?', [$key, $owner_key_id]);
  
  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["mismatch_check"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || empty($data["key"]) || !isset($data["title"]) || !isset($data["content"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $key = $data["key"];
  $title = $data["title"];
  $content = $data["content"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //check mismatch
  $result = db_query('SELECT `title`, `content` FROM `notes` WHERE `key`=? AND `owner_key_id`=?', [$key, $owner_key_id]);
  if ($result->num_rows == 0) { $script_result = "success"; end_script(DH::enc("ok", $enc_key, $enc_iv)); }
  $row = $result->fetch_assoc();

  if ($title != $row["title"] || $content != $row["content"]) { $script_result = "success"; end_script("0"); }

  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["password_change"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || empty($data["key"]) || !isset($data["password"]) || !isset($data["old_password"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $key = $data["key"];
  $password = $data["password"];
  $old_password = $data["old_password"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //check if this row exists
  $result = db_query('SELECT COUNT(*) FROM `notes` WHERE `key`=? AND `owner_key_id`=?', [$key, $owner_key_id]);
  $exists = intval($result->fetch_row()[0]) > 0;

  //if exists, verify old_password and update, if not, return ok
  if ($exists)
  {
    //check if old_password is correct
    $result = db_query('SELECT COUNT(*) FROM `notes` WHERE `key`=? AND `password`=? AND `owner_key_id`=?', [$key, $old_password, $owner_key_id]);
    $exists = intval($result->fetch_row()[0]) > 0;

    //if old_password is correct, update
    if ($exists)
    {
      db_query('UPDATE `notes` SET `password`=?, `blur`=? WHERE `key`=? AND `owner_key_id`=?', [$password, !empty($password), $key, $owner_key_id]);
    }
    //if not, fail
    else { $script_result = "old password wrong"; end_script("0"); }
  }

  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["edit_blur"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || empty($data["key"]) || !isset($data["blur"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $key = $data["key"];
  $blur = $data["blur"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //check if this row exists
  $result = db_query('SELECT COUNT(*) FROM `notes` WHERE `key`=? AND `owner_key_id`=?', [$key, $owner_key_id]);
  $exists = intval($result->fetch_row()[0]) > 0;

  //if exists, update
  if ($exists)
  {
    db_query('UPDATE `notes` SET `blur`=? WHERE `key`=? AND `owner_key_id`=?', [$blur, $key, $owner_key_id]);
  }
  //if not, fail
  else { $script_result = "note not found"; end_script("0"); }

  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["edit_color"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || empty($data["key"]) || !isset($data["color"])  || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $key = $data["key"];
  $color = $data["color"] == "null" ? null : $data["color"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //check if this row exists
  $result = db_query('SELECT COUNT(*) FROM `notes` WHERE `key`=? AND `owner_key_id`=?', [$key, $owner_key_id]);
  $exists = intval($result->fetch_row()[0]) > 0;

  //if exists, update
  if ($exists)
  {
    db_query('UPDATE `notes` SET `color`=? WHERE `key`=? AND `owner_key_id`=?', [$color, $key, $owner_key_id]);
  }
  //if not, fail
  else { $script_result = "note not found"; end_script("0"); }

  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["update_next_note_key"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || empty($data["key"]) || !isset($data["next_key"])  || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $key = $data["key"];
  $next_note_key = $data["next_key"] == "null" ? null : $data["next_key"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //check if this row exists
  $result = db_query('SELECT COUNT(*) FROM `notes` WHERE `key`=? AND `owner_key_id`=?', [$key, $owner_key_id]);
  $exists = intval($result->fetch_row()[0]) > 0;

  //if exists, update
  if ($exists)
  {
    db_query('UPDATE `notes` SET `next_note_key`=? WHERE `key`=? AND `owner_key_id`=?', [$next_note_key, $key, $owner_key_id]);
  }
  //if not, fail
  else { $script_result = "note not found"; end_script("0"); }

  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["update_last_note_key"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || empty($data["key"]) || !isset($data["last_key"])  || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $key = $data["key"];
  $last_note_key = $data["last_key"] == "null" ? null : $data["last_key"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //check if this row exists
  $result = db_query('SELECT COUNT(*) FROM `notes` WHERE `key`=? AND `owner_key_id`=?', [$key, $owner_key_id]);
  $exists = intval($result->fetch_row()[0]) > 0;

  //if exists, update
  if ($exists)
  {
    db_query('UPDATE `notes` SET `last_note_key`=? WHERE `key`=? AND `owner_key_id`=?', [$last_note_key, $key, $owner_key_id]);
  }
  //if not, fail
  else { $script_result = "note not found"; end_script("0"); }

  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["edit_default_note_color"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || !isset($data["color"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $color = $data["color"] == "null" ? null : $data["color"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //update
  db_query('UPDATE `owners` SET `default_note_color`=? WHERE `key`=?', [$color, $owner_key]);

  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["update_first_note_key"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || !isset($data["new_key"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $new_key = $data["new_key"] == "null" ? null : $data["new_key"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //update
  db_query('UPDATE `owners` SET `first_note_key`=? WHERE `key`=?', [$new_key, $owner_key]);

  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["get_variables"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();
  
  if (empty($data["ownerKey"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //select all from variables
  $result = db_query('SELECT * FROM `variables`');
  $vars = array();
  while ($row = $result->fetch_assoc()) { $vars[$row["key"]] = $row["value"]; }

  //add fields from owners tables
  $result = db_query('SELECT `default_note_color`, `first_note_key` FROM `owners` WHERE `key`=?', [$owner_key]);
  if ($result->num_rows == 0) { $script_result = "this should never happen - 158545263"; end_script("0"); }
  $row = $result->fetch_assoc();
  $vars["default_note_color"] = $row["default_note_color"];
  $vars["first_note_key"] = $row["first_note_key"];

  $script_result = "success";
  end_script(DH::enc(json_encode($vars), $enc_key, $enc_iv));
}
else if (isset($_GET["check_owner_key"]))
{
  get_data_from_post();
  $data = decrypt_body();
  
  if (empty($data["ownerKey"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);

  $result = db_query('SELECT COUNT(*) FROM `owners` WHERE `key`=?', [$owner_key]);
  $exists = intval($result->fetch_row()[0]) > 0;
  $script_result = "success";
  end_script($exists ? DH::enc("ok", $enc_key, $enc_iv) : "0");
}
else if (isset($_GET["init_owner_key"]))
{
  get_data_from_post();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);

  //check if this key already exists
  $result = db_query('SELECT COUNT(*) FROM `owners` WHERE `key`=?', [$owner_key]);
  if (intval($result->fetch_row()[0]) > 0) { $script_result = "i think this should never happen - 98741001"; end_script("0"); }

  $result = db_query('INSERT INTO `owners` (`key`) VALUES (?)', [$owner_key]);
  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["init_session"]))
{
  $data = json_decode(file_get_contents("php://input"), true);
  
  if (!isset($data["client_pub"])) { $script_result = "missing data field"; end_script("0"); }
  $client_pub = $data["client_pub"];

  $server_priv = rand(0, 2147483647);
  $server_pub = DH::pow_mod_p(DH::$g, $server_priv);

  $final_key = DH::pow_mod_p($client_pub, $server_priv);
  $enc_key = DH::gen_random($final_key);
  $enc_iv = DH::gen_random($final_key, 16, 133);
  $session = bin2hex(random_bytes(16)); //32 chars

  $result = db_query('INSERT INTO `sessions` (`session`, `enc_key`, `enc_iv`) VALUES (?, ?, ?)', [$session, $enc_key, $enc_iv]);
  $script_result = "success";
  end_script(json_encode(array("server_pub" => $server_pub, "session" => $session)));
}
else if (isset($_GET["version_check"]))
{
  get_data_from_post();
  $data = decrypt_body();

  if (empty($data["current_ver"]) || !isset($data["is_dev"]) || empty($data["platform"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $current_ver = $data["current_ver"];
  $is_dev = $data["is_dev"];
  $platform = $data["platform"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);

  if ($is_dev)
  {
    db_query('UPDATE `sessions` SET `version_check_done`=? WHERE `session`=?', [true, $session]);
    $script_result = "success";
    end_script(DH::enc("ok", $enc_key, $enc_iv));
  }

  $result = db_query('SELECT `latest_ver`, `oldest_allowed_ver`, `latest_link`, `instructions`, `instructions_link` FROM `config` WHERE `platform`=?', [$platform]);
  if ($result->num_rows == 0)
  {
    db_query('UPDATE `sessions` SET `version_check_done`=?, `revoked`=? WHERE `session`=?', [true, true, $session]);
    $script_result = "success";
    end_script(DH::enc("unsupported", $enc_key, $enc_iv));
  }

  $row = $result->fetch_assoc();
  $latest_ver = $row["latest_ver"];
  $oldest_allowed_ver = $row["oldest_allowed_ver"];
  $latest_link = $row["latest_link"];
  $instructions = $row["instructions"];
  $instructions_link = $row["instructions_link"];

  //check if config filled out
  $got_oldest = !empty($oldest_allowed_ver);
  $got_latest = !empty($latest_ver);
  if (!$got_oldest && !$got_latest)
  {
    db_query('UPDATE `sessions` SET `version_check_done`=? WHERE `session`=?', [true, $session]);
    $script_result = "success";
    end_script(DH::enc("ok", $enc_key, $enc_iv));
  }  
  $current_ver = preg_replace("/[^0-9]/", "", $current_ver);
  $can_ignore = false;

  if ($got_oldest) { $oldest_allowed_ver = preg_replace("/[^0-9]/", "", $oldest_allowed_ver); }
  if (!$got_oldest || $current_ver >= $oldest_allowed_ver) //oldest allowed not set or good
  {
    $can_ignore = true;

    if ($got_latest) { $latest_ver = preg_replace("/[^0-9]/", "", $latest_ver); }
    if (!$got_latest || $current_ver == $latest_ver) //latest not set or good
    {
      db_query('UPDATE `sessions` SET `version_check_done`=? WHERE `session`=?', [true, $session]);
      $script_result = "success";
      end_script(DH::enc("ok", $enc_key, $enc_iv));
    }
  }

  //not latest or under the oldest allowed version
  db_query('UPDATE `sessions` SET `version_check_done`=?, `revoked`=? WHERE `session`=?', [true, !$can_ignore, $session]);
  $script_result = "success";
  end_script(DH::enc(json_encode(array("can_ignore" => $can_ignore, "latest_ver" => $row["latest_ver"], "latest_link" => $row["latest_link"], "instructions" => $row["instructions"], "instructions_link" => $row["instructions_link"])), $enc_key, $enc_iv));
}
else if (isset($_GET["captcha"]))
{
  $period_length_seconds = 60;
  $max_request_per_period = 3;

  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || !isset($data["request_id"]) || !isset($data["success"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $request_id = $data["request_id"];
  $success = $data["success"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  $result = db_query('SELECT `captcha_done` FROM `owners` WHERE `key`=?', [$owner_key]);
  if ($result->num_rows == 0) { $script_result = "this never happens but lets keep it here - 91011"; end_script("0"); }
  $row = $result->fetch_assoc();
  $captcha_done = $row["captcha_done"];

  if ($captcha_done == 0)
  {
    //get current status from captcha table
    $result = db_query('SELECT *, UNIX_TIMESTAMP(`time`) AS `time` FROM `captcha` WHERE `owner_key_id`=? ORDER BY `id` DESC LIMIT ?', [$owner_key_id, $max_request_per_period]);
    $oldest_time = 0;
    $rows = 0;
    while ($row = $result->fetch_assoc())
    {
      $oldest_time = $row["time"];
      ++$rows;
    }
    $diff = time() - $oldest_time;
    if ($rows < $max_request_per_period || $diff >= $period_length_seconds)
    {
      $result = db_query('INSERT INTO `captcha` (`owner_key_id`, `success`) VALUES (?, ?)', [$owner_key_id, $success]);
    }
    else
    {
      $script_result = "success";
      end_script(DH::enc(strval($period_length_seconds - $diff), $enc_key, $enc_iv));
    }

    if ($success) { db_query('UPDATE `owners` SET `captcha_done`=1 WHERE `id`=?', [$owner_key_id]); }
  }

  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["get_msgs"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //prepare buffer
  $msgs = array();
  
  //select all from messages if in messages_target target_all true or target_owner_id references id of owner_key in owners table
  $result = db_query('SELECT *, UNIX_TIMESTAMP(`creation_date`) AS `creation_date` FROM `messages` WHERE `id` IN (SELECT `message_id` FROM `messages_target` WHERE `target_all`=1 OR `target_owner_key_id`=?)', [$owner_key_id]);
  if ($result->num_rows > 0)
  {
    //put into buffer
    while ($row = $result->fetch_assoc()) { $msgs[] = $row; }

    //prepare member inside of msgs array to hold seen status
    foreach ($msgs as &$m) { $m["seen"] = 0; }
    
    //seen status (row not exist = not seen; exists = has date when seen)
    $seen = array();
    $result = db_query('SELECT * FROM `messages_seen` WHERE `owner_key_id`=?', [$owner_key_id]);
    if ($result->num_rows > 0)
    {
      //put into buffer
      while ($row = $result->fetch_assoc()) { $seen[] = $row; }

      //make hash table essentially of seen msgs
      $seen_lookup = array_column($seen, 'message_id', 'message_id'); //we want message_id value and also to make it the key

      //now unite both buffers into a one
      foreach ($msgs as &$m) { if (isset($seen_lookup[$m["id"]])) { $m["seen"] = 1; } }
    }

    //should we pop up on start
    $pop_up_seen = array();
    $result = db_query('SELECT * FROM `messages_pop_up_seen` WHERE `owner_key_id`=?', [$owner_key_id]);
    if ($result->num_rows > 0)
    {
      //put into buffer
      while ($row = $result->fetch_assoc()) { $pop_up_seen[] = $row; }

      //make hash table essentially of seen msgs
      $pop_up_seen_lookup = array_column($pop_up_seen, 'message_id', 'message_id'); //we want message_id value and also to make it the key

      //now unite both buffers into a one
      foreach ($msgs as &$m) { if (isset($pop_up_seen_lookup[$m["id"]])) { $m["pop_up_on_start"] = 0; } }
    }
  }

  //now also add feedback into the array
  $result = db_query('SELECT *, UNIX_TIMESTAMP(`creation_date`) AS `creation_date` FROM `feedback` WHERE `owner_key_id`=(SELECT `id` FROM `owners` WHERE `key`=?)', [$owner_key]);
  if ($result->num_rows > 0)
  {
    //put into an array
    $feedback = array();
    while ($row = $result->fetch_assoc()) { $feedback[] = $row; }

    //add into original array
    foreach ($feedback as $f)
    {
      //convert table structure of feedback to structure of messages so its united
      array_push($msgs, array("title"=>"Your Feedback", "content"=>$f["content"], "creation_date"=>$f["creation_date"], "seen_by_dev"=>$f["seen_by_dev"], "seen"=>1));
    }
  }

  $script_result = "success";
  end_script(DH::enc(json_encode($msgs), $enc_key, $enc_iv));
}
else if (isset($_GET["send_feedback"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || !isset($data["content"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $content = $data["content"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //insert
  $time = time();
  $result = db_query('INSERT INTO `feedback` (`content`, `owner_key_id`, `creation_date`) VALUES (?, ?, FROM_UNIXTIME(?))', [$content, $owner_key_id, $time]);
  $script_result = "success";
  end_script(DH::enc(json_encode(array("ok", $time)), $enc_key, $enc_iv));
}
else if (isset($_GET["msg_seen"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || !isset($data["message_id"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $message_id = $data["message_id"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //check if exists
  $result = db_query('SELECT COUNT(*) FROM `messages_seen` WHERE `message_id`=? AND `owner_key_id`=?', [$message_id, $owner_key_id]);
  if (intval($result->fetch_row()[0]) > 0) { $script_result = "message already seen"; end_script("0"); }

  //mark as read
  $result = db_query('INSERT INTO `messages_seen` (`message_id`, `owner_key_id`) VALUES (?, ?)', [$message_id, $owner_key_id]);
  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else if (isset($_GET["popup_seen"]))
{
  get_data_from_post();
  check_session();
  $data = decrypt_body();

  if (empty($data["ownerKey"]) || !isset($data["message_id"]) || !isset($data["request_id"])) { $script_result = "missing data field"; end_script("0"); }
  $owner_key = $data["ownerKey"];
  $message_id = $data["message_id"];
  $request_id = $data["request_id"];

  check_request_id_exists($request_id);
  $owner_key_id = get_owner_key_id($owner_key);

  //check if exists
  $result = db_query('SELECT COUNT(*) FROM `messages_pop_up_seen` WHERE `message_id`=? AND `owner_key_id`=?', [$message_id, $owner_key_id]);
  if (intval($result->fetch_row()[0]) > 0) { $script_result = "popup already seen"; end_script("0"); }

  //mark as read
  $result = db_query('INSERT INTO `messages_pop_up_seen` (`message_id`, `owner_key_id`) VALUES (?, ?)', [$message_id, $owner_key_id]);
  $script_result = "success";
  end_script(DH::enc("ok", $enc_key, $enc_iv));
}
else
{
  $script_result = "no valid action detected";
  end_script("0");
}
$script_result = "this should never happen";
end_script("0");
