<?php
// Configurar cabeceras para permitir peticiones desde la App
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Incluir tu archivo de configuración (PDO)
require_once __DIR__ . '/../includes/config.php';

$response = [];

// Verificar que nos llegue el ID de la mascota (ej: ?pet_id=1)
if (isset($_GET['pet_id']) && !empty($_GET['pet_id'])) {
    $pet_id = intval($_GET['pet_id']);

    try {
        // ----- 1. OBTENER DATOS DE LA MASCOTA -----
        $queryPet = "SELECT p.*, pt.name as type_name, b.name as breed_name 
                     FROM pets p
                     LEFT JOIN pet_types pt ON p.type_id = pt.id
                     LEFT JOIN breeds b ON p.breed_id = b.id
                     WHERE p.id = :pet_id";
        $stmt = $conn->prepare($queryPet);
        $stmt->execute(['pet_id' => $pet_id]);
        $pet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pet) {
            $response['success'] = false;
            $response['message'] = 'Mascota no encontrada';
            echo json_encode($response);
            exit;
        }

        // ----- 2. OBTENER CITAS -----
        $queryAppointments = "SELECT * FROM appointments WHERE pet_id = :pet_id ORDER BY appointment_date DESC";
        $stmt = $conn->prepare($queryAppointments);
        $stmt->execute(['pet_id' => $pet_id]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ----- 3. OBTENER VACUNAS -----
        $queryVaccines = "SELECT v.*, vt.name as vaccine_name 
                          FROM vaccines v
                          LEFT JOIN vaccine_types vt ON v.vaccine_type_id = vt.id
                          WHERE v.pet_id = :pet_id 
                          ORDER BY v.application_date DESC";
        $stmt = $conn->prepare($queryVaccines);
        $stmt->execute(['pet_id' => $pet_id]);
        $vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ----- 4. OBTENER CONSULTAS -----
        $queryConsultations = "SELECT * FROM consultations WHERE pet_id = :pet_id ORDER BY consultation_date DESC";
        $stmt = $conn->prepare($queryConsultations);
        $stmt->execute(['pet_id' => $pet_id]);
        $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ----- 5. OBTENER TRATAMIENTOS -----
        $queryTreatments = "SELECT * FROM treatments WHERE pet_id = :pet_id ORDER BY created_at DESC";
        $stmt = $conn->prepare($queryTreatments);
        $stmt->execute(['pet_id' => $pet_id]);
        $treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ----- ARMAR LA RESPUESTA -----
        $response['success'] = true;
        $response['data'] = [
            'pet' => $pet,
            'appointments' => $appointments,
            'vaccines' => $vaccines,
            'consultations' => $consultations,
            'treatments' => $treatments
        ];

    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Error en la consulta: ' . $e->getMessage();
    }
} else {
    $response['success'] = false;
    $response['message'] = 'Falta el parámetro pet_id. Ejemplo: ?pet_id=1';
}

echo json_encode($response);
?>
