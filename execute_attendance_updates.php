<?php
require_once 'config/database.php';

function executeSQLFile($pdo, $filename) {
    try {
        // Check if file exists
        if (!file_exists($filename)) {
            throw new Exception("SQL file not found: $filename");
        }

        // Read the SQL file
        $sql = file_get_contents($filename);
        if ($sql === false) {
            throw new Exception("Failed to read SQL file");
        }

        // Split the SQL file into individual queries
        $queries = array_filter(array_map('trim', explode(';', $sql)));

        // Start transaction
        $pdo->beginTransaction();

        // Execute each query
        foreach ($queries as $query) {
            if (!empty($query)) {
                $pdo->exec($query);
            }
        }

        // Commit transaction
        $pdo->commit();

        return true;
    } catch (Exception $e) {
        // Rollback transaction if it was started
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

try {
    // Execute the SQL file
    $result = executeSQLFile($pdo, 'sql/attendance_updates.sql');
    
    if ($result) {
        echo "Attendance updates applied successfully!\n";
        
        // Verify tables were created/updated
        $tables = ['attendance_policy', 'attendance_notifications', 'attendance_reports'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✓ Table '$table' exists\n";
            } else {
                echo "✗ Table '$table' was not created\n";
            }
        }

        // Verify attendance table columns
        $stmt = $pdo->query("SHOW COLUMNS FROM attendance");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $new_columns = ['total_hours', 'auto_status', 'ip_address', 'device_info'];
        
        foreach ($new_columns as $column) {
            if (in_array($column, $columns)) {
                echo "✓ Column '$column' exists in attendance table\n";
            } else {
                echo "✗ Column '$column' was not added to attendance table\n";
            }
        }
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?> 