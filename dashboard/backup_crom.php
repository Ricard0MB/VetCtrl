<?php
// backup_cron.php
require_once '../includes/config.php'; // $conn es un objeto PDO

// Verificar si es una ejecución programada (por clave secreta)
$secret_key = $_GET['key'] ?? '';
$valid_key = 'TU_CLAVE_SECRETA_AQUI'; // Cambia esto por una clave segura

if ($secret_key !== $valid_key) {
    die('Acceso no autorizado');
}

// Función de backup (adaptada a PDO)
function backupDatabase($conn) {
    $backup_path = '../backups/';
    
    if (!file_exists($backup_path)) {
        mkdir($backup_path, 0777, true);
    }
    
    $date = date('Y-m-d_H-i-s');
    $filename = $backup_path . 'auto_backup_' . $date . '.sql';
    $zipname = $backup_path . 'auto_backup_' . $date . '.zip';
    
    // Obtener todas las tablas
    $tables = array();
    $stmt = $conn->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $return = '';
    foreach ($tables as $table) {
        // Obtener CREATE TABLE
        $stmtCreate = $conn->query("SHOW CREATE TABLE `$table`");
        $rowCreate = $stmtCreate->fetch(PDO::FETCH_NUM);
        $return .= "\n\n" . $rowCreate[1] . ";\n\n";
        
        // Obtener datos
        $stmtData = $conn->query("SELECT * FROM `$table`");
        $num_fields = $stmtData->columnCount();
        
        while ($rowData = $stmtData->fetch(PDO::FETCH_NUM)) {
            $return .= "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $num_fields; $j++) {
                $value = $rowData[$j];
                $value = addslashes($value);
                $value = str_replace("\n", "\\n", $value);
                $return .= isset($rowData[$j]) ? '"' . $value . '"' : '""';
                if ($j < ($num_fields - 1)) $return .= ',';
            }
            $return .= ");\n";
        }
        $return .= "\n";
    }
    
    // Guardar archivo SQL
    $handle = fopen($filename, 'w+');
    fwrite($handle, $return);
    fclose($handle);
    
    // Crear ZIP
    $zip = new ZipArchive();
    if ($zip->open($zipname, ZipArchive::CREATE) === TRUE) {
        $zip->addFile($filename, basename($filename));
        $zip->close();
        unlink($filename); // Eliminar el archivo SQL temporal
        
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
