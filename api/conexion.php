<?php
// api/conexion.php

/**
 * Conexión a la base de datos PostgreSQL en Supabase
 * utilizando PDO para mayor seguridad y robustez.
 */

// Credenciales de Supabase (Reemplazar con tus datos)
$host = 'aws-1-us-east-1.pooler.supabase.com'; // HOST (Pooler)
$db_name = 'postgres';                             // DB_NAME
$user = 'postgres.pusrebyszbtyefcjmvsh';           // USER (Pooler user)
$password = 'DevMontero#26';                       // PASSWORD
$port = '6543';                                    // PORT (Pooler port)

try {
    // DSN (Data Source Name) adaptado para PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$db_name;user=$user;password=$password";
    
    // Opciones para la conexión PDO
    $options = [
        // Manejo de errores mediante excepciones (robusto)
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
        // Retornar siempre resultados como arrays asociativos para fácil conversión a JSON
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
        // IMPORTANTE: Habilitar emulación de preparadas para compatibilidad con Supavisor (puerto 6543)
        PDO::ATTR_EMULATE_PREPARES   => true,                  
    ];

    // Instancia de la conexión PDO
    $pdo = new PDO($dsn, null, null, $options); 
    
    // Si la conexión es exitosa, $pdo estará disponible para otros archivos que incluyan este script.
} catch (PDOException $e) {
    // Captura de errores (try/catch)
    // En un entorno de producción, evita mostrar $e->getMessage() al usuario, regístralo en un log.
    http_response_code(500);
    die(json_encode([
        'error' => 'Error crítico de conexión a la base de datos',
        'detalle' => $e->getMessage() // QUITAR ESTA LÍNEA EN PRODUCCIÓN
    ]));
}
?>
