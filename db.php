<?php
$dbHost = '127.0.0.1';
$dbPort = 3306;
$dbUser = 'root';
$dbPass = '';
$dbName = 'apex_task1';
$storageFile = __DIR__ . '/portfolio_store.json';
$dbError = '';

// Disable mysqli exceptions so pages can render actionable setup guidance.
mysqli_report(MYSQLI_REPORT_OFF);

$connectionCandidates = [
    ['host' => '127.0.0.1', 'port' => 3306],
    ['host' => 'localhost', 'port' => 3306],
    ['host' => '127.0.0.1', 'port' => 3307],
    ['host' => 'localhost', 'port' => 3307],
];

$conn = false;

foreach ($connectionCandidates as $candidate) {
    $conn = @mysqli_connect($candidate['host'], $dbUser, $dbPass, '', (int)$candidate['port']);

    if ($conn) {
        $dbHost = $candidate['host'];
        $dbPort = (int)$candidate['port'];
        break;
    }
}

if (!$conn) {
    $dbError = 'Database server connection failed: ' . mysqli_connect_error() . '. Tried 127.0.0.1/localhost on ports 3306 and 3307.';
}

if ($conn) {
    if (!mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$dbName`")) {
        $dbError = 'Unable to create database: ' . mysqli_error($conn);
        mysqli_close($conn);
        $conn = false;
    } elseif (!mysqli_select_db($conn, $dbName)) {
        $dbError = 'Unable to select database: ' . mysqli_error($conn);
        mysqli_close($conn);
        $conn = false;
    } else {
        mysqli_set_charset($conn, 'utf8mb4');

        $createUsers = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
        ";

        $createProfiles = "
        CREATE TABLE IF NOT EXISTS profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            password_length INT NOT NULL,
            role VARCHAR(50) NOT NULL,
            gender VARCHAR(20) NOT NULL,
            bio TEXT,
            interests VARCHAR(255),
            newsletter TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
        ";

        mysqli_query($conn, $createUsers);
        mysqli_query($conn, $createProfiles);
    }
}

function portfolio_storage_defaults()
{
    return [
        'next_id' => 1,
        'records' => [],
    ];
}

function portfolio_storage_read($storageFile)
{
    if (!file_exists($storageFile)) {
        return portfolio_storage_defaults();
    }

    $decoded = json_decode((string)file_get_contents($storageFile), true);

    if (!is_array($decoded)) {
        return portfolio_storage_defaults();
    }

    return array_merge(portfolio_storage_defaults(), $decoded);
}

function portfolio_storage_write($storageFile, array $data)
{
    file_put_contents($storageFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function portfolio_storage_normalize_record(array $record)
{
    $record['profile_id'] = (int)($record['profile_id'] ?? 0);
    $record['password_length'] = (int)($record['password_length'] ?? 0);
    $record['newsletter'] = (int)($record['newsletter'] ?? 0);
    $record['created_at'] = (string)($record['created_at'] ?? date('Y-m-d H:i:s'));

    return $record;
}

function portfolio_storage_upsert_record($storageFile, array $record)
{
    $data = portfolio_storage_read($storageFile);
    $record = portfolio_storage_normalize_record($record);

    foreach ($data['records'] as $index => $existing) {
        if (isset($existing['email']) && strcasecmp((string)$existing['email'], (string)$record['email']) === 0) {
            $record['profile_id'] = (int)$existing['profile_id'];
            $data['records'][$index] = array_merge($existing, $record);
            portfolio_storage_write($storageFile, $data);
            return $data['records'][$index]['profile_id'];
        }
    }

    $record['profile_id'] = (int)$data['next_id'];
    $data['next_id'] = $record['profile_id'] + 1;
    $data['records'][] = $record;
    portfolio_storage_write($storageFile, $data);

    return $record['profile_id'];
}

function portfolio_storage_delete_record($storageFile, $profileId)
{
    $data = portfolio_storage_read($storageFile);
    $data['records'] = array_values(array_filter($data['records'], function ($record) use ($profileId) {
        return (int)($record['profile_id'] ?? 0) !== (int)$profileId;
    }));
    portfolio_storage_write($storageFile, $data);
}

function portfolio_storage_update_record($storageFile, $profileId, $role, $bio)
{
    $data = portfolio_storage_read($storageFile);

    foreach ($data['records'] as $index => $record) {
        if ((int)($record['profile_id'] ?? 0) === (int)$profileId) {
            $data['records'][$index]['role'] = $role;
            $data['records'][$index]['bio'] = $bio;
            portfolio_storage_write($storageFile, $data);
            break;
        }
    }
}

function portfolio_storage_all_records($storageFile)
{
    $data = portfolio_storage_read($storageFile);
    $records = $data['records'];

    usort($records, function ($left, $right) {
        return (int)($right['profile_id'] ?? 0) <=> (int)($left['profile_id'] ?? 0);
    });

    return $records;
}
?>