<?php
// api/productos.php

// 1. Configurar cabeceras (Headers) HTTP limpias
header('Content-Type: application/json; charset=utf-8'); // Responder en formato JSON
header('Access-Control-Allow-Origin: *');                // Permitir CORS (En producción, restringir al dominio del frontend)
header('Access-Control-Allow-Methods: GET, OPTIONS');    // Métodos permitidos
header('Access-Control-Allow-Headers: Content-Type');

// Manejo de la solicitud OPTIONS para CORS (Preflight request)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Requerir la conexión a la base de datos
require_once 'conexion.php';

try {
    // 3. Consulta de los datos del negocio (Configuración visual y general)
    // Asumimos que existe una tabla 'negocio' con id = 1
    $stmtNegocio = $pdo->prepare("SELECT nombre, logo_url, color_corporativo, telefono_whatsapp FROM negocio LIMIT 1");
    $stmtNegocio->execute();
    $negocio = $stmtNegocio->fetch();

    // Valores por defecto si la base de datos está vacía
    if (!$negocio) {
        $negocio = [
            'nombre' => 'PronttoGo Store',
            'logo_url' => '',
            'color_corporativo' => '#3b82f6', // Color Tailwind Blue 500 por defecto
            'telefono_whatsapp' => '584120000000'
        ];
    }

    // 4. Consulta relacional para traer categorías con sus productos activos (JOIN y agrupación)
    // Utilizamos JSON_AGG y JSON_BUILD_OBJECT nativos de PostgreSQL para traer una estructura anidada y eficiente
    $sqlCategorias = "
        SELECT 
            c.id AS categoria_id,
            c.nombre AS categoria_nombre,
            COALESCE(
                json_agg(
                    json_build_object(
                        'id', p.id,
                        'nombre', p.nombre,
                        'descripcion', p.descripcion,
                        'precio', p.precio,
                        'imagen_url', p.imagen_url
                    )
                ) FILTER (WHERE p.id IS NOT NULL), '[]'
            ) AS productos
        FROM categorias c
        LEFT JOIN productos p ON c.id = p.categoria_id AND p.activo = true
        GROUP BY c.id, c.nombre
        ORDER BY c.id ASC;
    ";
    
    $stmtCategorias = $pdo->prepare($sqlCategorias);
    $stmtCategorias->execute();
    $categoriasRaw = $stmtCategorias->fetchAll();
    
    // 5. Procesamiento y sanitización de datos (evitar inyecciones XSS en el frontend)
    $categorias = array_map(function($cat) {
        $cat['categoria_nombre'] = htmlspecialchars($cat['categoria_nombre'], ENT_QUOTES, 'UTF-8');
        
        // Decodificar el JSON de productos retornado por Postgres
        $productos = json_decode($cat['productos'], true);
        
        foreach ($productos as &$prod) {
            $prod['nombre'] = htmlspecialchars($prod['nombre'], ENT_QUOTES, 'UTF-8');
            $prod['descripcion'] = htmlspecialchars($prod['descripcion'], ENT_QUOTES, 'UTF-8');
            // Validar que las URLs sean seguras
            $prod['imagen_url'] = filter_var($prod['imagen_url'], FILTER_SANITIZE_URL);
        }
        $cat['productos'] = $productos;
        return $cat;
    }, $categoriasRaw);

    // Sanitizar los datos del negocio
    $negocioSanitizado = [
        'nombre' => htmlspecialchars($negocio['nombre'], ENT_QUOTES, 'UTF-8'),
        'logo_url' => filter_var($negocio['logo_url'], FILTER_SANITIZE_URL),
        'color_corporativo' => htmlspecialchars($negocio['color_corporativo'], ENT_QUOTES, 'UTF-8'),
        'telefono_whatsapp' => htmlspecialchars($negocio['telefono_whatsapp'], ENT_QUOTES, 'UTF-8')
    ];

    // 6. Armar la respuesta estructurada final
    $respuesta = [
        'negocio' => $negocioSanitizado,
        'categorias' => $categorias
    ];

    // 7. Responder con el JSON sanitizado
    echo json_encode($respuesta);

} catch (PDOException $e) {
    // Si hay un error en las consultas, se devuelve un error 500 HTTP con el detalle
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al consultar la base de datos', 
        'detalle' => $e->getMessage() // En producción, ocultar el $e->getMessage() por seguridad
    ]);
}
?>
