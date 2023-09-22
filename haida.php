<?php

/**
 * Haida.
 */

const SERVER_NAME = "localhost";
const USER_NAME = "root";
const PASSWORD = "felicidad";
const DATABASE = "haida";
const ROWS_PER_STEP = 10;
const TIMEZONE = 'America/Bogota';
const TIMEZONE_TIMESTAMP_OFFSET = 18000;

date_default_timezone_set(TIMEZONE);

session_start();

try {

  $conn = init_database_connection();

  $data = [];

  if ($_SERVER["REQUEST_METHOD"] == 'POST') {

    $angularJSData = json_decode(file_get_contents("php://input"));
    $angularJSData = (array) $angularJSData;

    if (isset($angularJSData['upstream'])) {


      $new_accounts = [];
      $new_rows = [];
      $deleted_rows = [];
      $updated_rows = [];
      foreach ($angularJSData['upstream'] as $item) {

        if ($item->status == 'added') {
          insert($conn, $item, $new_accounts, $new_rows);
        }

        if ($item->status == 'edited') {
          update($conn, $item, $new_accounts, $updated_rows);
        }

        if ($item->status == 'deleted') {
          delete($conn, $item, $deleted_rows);
        }
      }

      $data = [
        'accounts' => $new_accounts,
        'sheet' => $new_rows,
        'deleted' => $deleted_rows,
        'updated' => $updated_rows,
      ];

    }
  }
  else {
    $date = [];
    if (isset($_GET['date_month']) && isset($_GET['date_year'])) {
      $date = [
        'month' => $_GET['date_month'],
        'year' => $_GET['date_year'],
      ];
    }
    else {
      $date = get_last_date_timestamp($conn);
      if (is_new_month($date)) {
        balance($conn);
      }
    }
    $data = get($conn, $date);
  }

  return_response($data, 200);

  $conn->close();
} 
catch (Exception $error) {

  $response = [
    'status' => $error->getCode(),
    'message' => $error->getMessage(),
  ];

  return_response($response, 500);
}

/**
 * Get
 */
function get($conn, $date) {

  $accounts = get_accounts($conn);

  $sheet = [];
  $date = $date == 0 ? get_current_date() : timestamp_to_array_date($date);
  $stmt = $conn->prepare("SELECT * FROM sheet where MONTH(FROM_UNIXTIME(date))=? and YEAR(FROM_UNIXTIME(date))=?");
  $stmt->bind_param('ii', $date['month'], $date['year']);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $sheet[] = $row;
    }
  }

  return [
    'accounts' => $accounts,
    'sheet' => $sheet,
    'last_account_id' => end($accounts)['id'],
  ];
}

/**
 * Insert
 */
function insert($conn, $item, &$new_accounts, &$new_rows) {

  $accounts = insert_accounts($conn, $item, $new_accounts);

  // $accounts = get_store('accounts_names') ?? [];
  // $new_account = FALSE;
  // if (!array_key_exists($item->toName, $accounts)) {
  //   if ($new_account = create_account($conn, $item->toName)) {
  //     $new_account['value'] = $item->value;
  //     $accounts[$item->toName] = $new_account['id'];
  //     $new_accounts[] = $new_account;
  //   }
  // }
  // if (!array_key_exists($item->fromName, $accounts)) {
  //   if ($new_account = create_account($conn, $item->fromName)) {
  //     $new_account['value'] = -1 * $item->value;
  //     $accounts[$item->fromName] = $new_account['id'];
  //     $new_accounts[] = $new_account;
  //   }
  // }
  // set_store('accounts_names', $accounts);
 
  $date = strtotime($item->year . '-' . $item->month . '-' . $item->day);
  $description = $item->description;
  $debit = $accounts[$item->toName];
  $credit = $accounts[$item->fromName];
  $value = $item->value;
  $stmt = $conn->prepare("INSERT INTO sheet (date, description, debit, credit, value) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param('isiid', $date, $description, $debit, $credit, $value);
  if ($stmt->execute()) {
    $new_rows[] = [
      'id' => $conn->insert_id,
      'date' => $date,
      'description' => $description,
      'debit' => $debit,
      'credit' => $credit,
      'value' => $value,
    ];
  };
}

/**
 * Insert accounts
 */
function insert_accounts($conn, $item, &$new_accounts) {
  $accounts = get_store('accounts_names') ?? [];
  $new_account = FALSE;
  if (!array_key_exists($item->toName, $accounts)) {
    if ($new_accounts_partial = create_account($conn, $item->toName, $item->value)) {
      foreach ($new_accounts_partial as $new_account) {
        $accounts[$new_account['name']] = $new_account['id'];
      }
      $new_accounts = $new_accounts + $new_accounts_partial;
    }
  }
  if (!array_key_exists($item->fromName, $accounts)) {
    if ($new_accounts_partial = create_account($conn, $item->fromName, $item->value)) {
      foreach ($new_accounts_partial as $new_account) {
        $accounts[$item->fromName] = $new_account['id'];
      }
      $new_accounts = $new_accounts + $new_accounts_partial;
    }
  }
  set_store('accounts_names', $accounts);
  return $accounts;
}

/**
 * Insert
 */
function update($conn, $item, &$new_accounts, &$updated_rows) {

  $accounts = insert_accounts($conn, $item, $new_accounts);
 
  $date = strtotime($item->year . '-' . $item->month . '-' . $item->day);
  $description = $item->description;
  $debit = $accounts[$item->toName];
  $credit = $accounts[$item->fromName];
  $value = $item->value;
  $stmt = $conn->prepare("UPDATE sheet SET date=?, description=?, debit=?, credit=?, value=?  WHERE id=?");
  $stmt->bind_param('isiidi', $date, $description, $debit, $credit, $value, $item->id);
  if ($stmt->execute()) {
    $updated_rows[] = [
      'id' => $item->id,
      'date' => $date,
      'description' => $description,
      'debit' => $debit,
      'credit' => $credit,
      'value' => $value,
    ];
  };
}

/**
 * Delete
 */
function delete($conn, $item, &$deleted_rows) {
  $stmt = $conn->prepare("DELETE FROM sheet WHERE id=?");
  $stmt->bind_param('i', $item->id);
  if ($stmt->execute()) {
    $deleted_rows[] = $item;
  }
}

/**
 * Init database connection
 */
function init_database_connection() {

  $conn = new mysqli(SERVER_NAME, USER_NAME, PASSWORD, DATABASE);

  if ($conn->connect_error) {
    throw new Exception("Connection failed: " . $conn->connect_error);
  }

  return $conn;
}

/**
 * Return response
 */
function return_response($response, $code) {

  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($response);
}

/**
 * Store
 */
function set_store($key, $data) {
  // warning: not safe for shared hosting.
  $_SESSION['haida']['store'][$key] = $data;
}

/**
 * Store
 */
function get_store($key) {
  return $_SESSION['haida']['store'][$key] ?? NULL;
}

/**
 * Insert
 */
function create_account($conn, $name, $value) {
  $accounts = get_store('accounts_names') ?? [];
  $names = explode(' ', $name);
  $pid = 0;
  $name = '';
  $new_accounts = [];
  $last_account_id = end($accounts);

  $stmt = $conn->prepare("INSERT INTO accounts (pid, name) VALUES (?, ?)");
  foreach ($names as $key => $piece) {

    if ($key > 0) {
      $pid = $last_account_id;
      $name .= ' ';
    }
    $name .= $piece;
    $stmt->bind_param('is', $pid, $name);
    if (!array_key_exists($name, $accounts)) {
      if ($stmt->execute()) {

        $new_account = [
          'id' => $conn->insert_id,
          'pid' => $pid,
          'name' => $name,
          'value'=> $value,
        ];
        $new_accounts[] = $new_account;
        $last_account_id = $conn->insert_id;
      }
    }
    else {
      $last_account_id = $accounts[$name];
    }
  }
  return $new_accounts;
}

/**
 * Calculate Account Value
 */
function getAccountValue($conn, $account_id) {
  $value = getLastAccountValueInBalance($conn, $account_id);
  addAccountValuesFromSheet($conn, $account_id, $value);
  return $value;
}

/**
 * Get Last Account Value In Balance
 */
function getLastAccountValueInBalance($conn, $account_id) {
  $stmt = $conn->prepare("SELECT * FROM balance where aid=? order by date desc limit 1");
  $stmt->bind_param('i', $account_id);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows > 0) {
    $value = (float) $result->fetch_assoc()['value'];
    return $value;
  }
  else {
    return 0;
  }
}

/**
 * Add Account Values From Sheet
 */
function addAccountValuesFromSheet($conn, $account_id, &$value) {
  $last_date = get_last_date_timestamp($conn);
  $stmt = $conn->prepare("SELECT * FROM sheet WHERE (debit=? OR credit=?) AND date>?");
  $stmt->bind_param('iii', $account_id, $account_id, $last_date);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      if ($row['debit'] == $account_id) {
        $value += $row['value'];
      }
      if ($row['credit'] == $account_id) {
        $value -= $row['value'];
      }
    }
  }
  $children = getAccountChildren($conn, $account_id);
  foreach ($children as $child) {
    addAccountValuesFromSheet($conn, $child['id'], $value);
  }
}

/**
 * Get Account Children
 */
function getAccountChildren($conn, $account_id) {
  $stmt = $conn->prepare("SELECT * FROM accounts WHERE pid=?");
  $stmt->bind_param('i', $account_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $accountsArray = array();
  while ($row = $result->fetch_assoc()) {
    $accountsArray[] = $row;
  }
  return $accountsArray;
}

/**
 * Get Last date
 */
// function get_last_date($conn) {
//   $sql = "SELECT MONTH(FROM_UNIXTIME(date)) as month, YEAR(FROM_UNIXTIME(date)) as year FROM balance order by id desc limit 1";
//   $result = $conn->query($sql);
//   if ($result->num_rows > 0) {
//     return $result->fetch_assoc();
//   }
//   return NULL;
// }

/**
 * Get Last date
 */
function get_last_date_timestamp($conn) {
  $sql = "SELECT date FROM balance order by id desc limit 1";
  $result = $conn->query($sql);
  if ($result->num_rows > 0) {
    $next_month_date = $result->fetch_assoc()['date'] + 86400;
    $next_month_date = $next_month_date - ($next_month_date % 86400) + TIMEZONE_TIMESTAMP_OFFSET; 
    return $next_month_date;
  }
  return 0;
}

/**
 * Get Last date
 */
function get_current_date() {
  return timestamp_to_array_date(time());
}

/**
 * timestamp to array date
 */
function timestamp_to_array_date($timestamp) {
  return [
    'month' => (int) date('m', $timestamp),
    'year' => (int) date('Y', $timestamp),
  ];
}

/**
 * Is new month
 */
function is_new_month($given_date) {
  $current_date = get_current_date();
  if ((int) date('Y', $given_date) < $current_date['year']) {
    return TRUE;
  }
  elseif ((int) date('m', $given_date) < $current_date['month']) {
    return TRUE;
  }
  else {
    return FALSE;
  }
}

/**
 * Get accounts
 */
function get_accounts($conn) {
  $accounts = [];
  $accounts_names = [];

  $sql = "SELECT * FROM accounts order by id";
  $result = $conn->query($sql);

  if ($result->num_rows > 0) {
    $key = 0;
    while ($row = $result->fetch_assoc()) {
      $accounts[$key] = $row;
      $accounts[$key]['value'] = getAccountValue($conn, $row['id']);
      $accounts_names[$row['name']] = $row['id'];
      $key++;
    }
  }

  set_store('accounts_names', $accounts_names);
  return $accounts;
}

/**
 * Balance
 */
function balance($conn) {
  $accounts = get_accounts($conn);
  foreach ($accounts as $account) {
    insert_balance($conn, $account);
  }
}

/**
 * Insert balance
 */
  function insert_balance($conn, $account) {
    $date = strtotime('last day of previous month');
    $stmt = $conn->prepare("INSERT INTO balance (date, aid, value) VALUES (?, ?, ?)");
    $stmt->bind_param('iid', $date, $account['id'], $account['value']);
    $stmt->execute();
  }

/**
 * in_array_r
 * see https://stackoverflow.com/questions/4128323/in-array-and-multidimensional-array
 */
function in_array_r($needle, $haystack, $strict = false) {
    foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && in_array_r($needle, $item, $strict))) {
            return true;
        }
    }

    return false;
}