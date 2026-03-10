<?php
// backup_cron.php
require_once '../includes/config.php';

// Verificar si es una ejecución programada (por clave secreta)
$secret_key = $_GET['key'] ?? '';
$valid_key = 'TU_CLAVE_SECRETA_AQUI'; // Cambia esto por una clave segura

if ($secret_key !== $valid_key) {
    die('Acceso no autorizado');
}

// Función de backup (misma que en backup_system.php)
function backupDatabase($conn) {
    $backup_path = '../backups/';
    
    if (!file_exists($backup_path)) {
        mkdir($backup_path, 0777, true);
    }
    
    $date = date('Y-m-d_H-i-s');
    $filename = $backup_path . 'auto_backup_' . $date . '.sql';
    $zipname = $backup_path . 'auto_backup_' . $date . '.zip';
    
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $return = '';
    foreach ($tables as $table) {
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch_row();
        $return .= "\n\n" . $row[1] . ";\n\n";
        
        $result = $conn->query("SELECT * FROM `$table`");
        $num_fields = $result->field_count;
        
        while ($row = $result->fetch_row()) {
            $return .= "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $num_fields; $j++) {
                $row[$j] = addslashes($row[$j]);
                $row[$j] = str_replace("\n", "\\n", $row[$j]);
                $return .= isset($row[$j]) ? '"' . $row[$j] . '"' : '""';
                if ($j < ($num_fields - 1)) $return .= ',';
            }
            $return .= ");\n";
        }
        $return .= "\n";
    }
    
    $handle = fopen($filename, 'w+');
    fwrite($handle, $return);
    fclose($handle);
    
    $zip = new ZipArchive();
    if ($zip->open($zipname, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($filename, basename($filename));
        $zip->close();
        unlink($filename);
        
        // Eliminar backups antiguos (mantener solo los últimos 30)
        $files = glob($backup_path . 'auto_backup_*.zip');
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        if (count($files) > 30) {
            for ($i = 30; $i < count($files); $i++) {
                unlink($files[$i]);
            }
        }
        echo "Backup automático completado: " . basename($zipname);
    } else {
        echo "Error al crear backup";
    }
}

backupDatabase($conn);
?>