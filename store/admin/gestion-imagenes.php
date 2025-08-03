<?php
require_once '../config.php';

// Verificar si el usuario es admin
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
} elseif($_SESSION['usuario_rol'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

// Verificar que se haya proporcionado un ID de producto válido
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID de producto no válido";
    header("Location: productos.php");
    exit;
}

$producto_id = (int)$_GET['id'];

// Obtener información del producto
$stmt = $conn->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$producto) {
    $_SESSION['error'] = "Producto no encontrado";
    header("Location: productos.php");
    exit;
}

// Obtener imágenes adicionales del producto
$imagenes_adicionales = [];
if(!empty($producto['imagenes_adicionales'])) {
    $imagenes_adicionales = json_decode($producto['imagenes_adicionales'], true);
}

// Procesar eliminación de imagen
if(isset($_GET['eliminar_imagen'])) {
    $imagen_a_eliminar = basename($_GET['eliminar_imagen']);
    
    if(($key = array_search($imagen_a_eliminar, $imagenes_adicionales)) !== false) {
        // Eliminar la imagen del servidor
        $ruta_imagen = '../assets/img/productos/' . $imagen_a_eliminar;
        if(file_exists($ruta_imagen)) {
            unlink($ruta_imagen);
        }
        
        // Eliminar la imagen del array
        unset($imagenes_adicionales[$key]);
        
        // Reindexar el array
        $imagenes_adicionales = array_values($imagenes_adicionales);
        
        // Actualizar la base de datos
        $imagenes_json = !empty($imagenes_adicionales) ? json_encode($imagenes_adicionales) : null;
        $stmt = $conn->prepare("UPDATE productos SET imagenes_adicionales = ? WHERE id = ?");
        $stmt->execute([$imagenes_json, $producto_id]);
        
        $_SESSION['mensaje'] = "Imagen eliminada correctamente";
        header("Location: gestion-imagenes.php?id=" . $producto_id);
        exit;
    }
}

// Procesar subida de nuevas imágenes
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['nuevas_imagenes'])) {
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
    $imagenes_subidas = [];
    
    foreach($_FILES['nuevas_imagenes']['name'] as $key => $nombre_archivo) {
        if($_FILES['nuevas_imagenes']['error'][$key] === UPLOAD_ERR_OK) {
            $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
            
            if(in_array($extension, $extensiones_permitidas)) {
                $nombre_unico = uniqid('product_extra_', true) . '.' . $extension;
                $ruta_destino = '../assets/img/productos/' . $nombre_unico;
                
                if(move_uploaded_file($_FILES['nuevas_imagenes']['tmp_name'][$key], $ruta_destino)) {
                    $imagenes_subidas[] = $nombre_unico;
                }
            }
        }
    }
    
    if(!empty($imagenes_subidas)) {
        // Combinar con las imágenes existentes
        $imagenes_adicionales = array_merge($imagenes_adicionales, $imagenes_subidas);
        $imagenes_json = json_encode($imagenes_adicionales);
        
        // Actualizar la base de datos
        $stmt = $conn->prepare("UPDATE productos SET imagenes_adicionales = ? WHERE id = ?");
        $stmt->execute([$imagenes_json, $producto_id]);
        
        $_SESSION['mensaje'] = "Imágenes agregadas correctamente";
        header("Location: gestion-imagenes.php?id=" . $producto_id);
        exit;
    } else {
        $_SESSION['error'] = "No se pudieron subir las imágenes";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Imágenes - Kalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #212529;
            color: white;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .img-thumbnail {
            width: 150px;
            height: 150px;
            object-fit: cover;
            margin: 5px;
        }
        
        .img-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        
        .img-wrapper {
            position: relative;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
        }
        
        .img-actions {
            position: absolute;
            top: 5px;
            right: 5px;
        }
        
        .preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .preview-thumbnail {
            max-width: 100px;
            max-height: 100px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
            
            .img-thumbnail {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Imágenes</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="productos.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver a Productos
                        </a>
                    </div>
                </div>
                
                <?php if(isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Imágenes del producto: <?php echo htmlspecialchars($producto['nombre']); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Imagen principal</h6>
                                <?php if(!empty($producto['imagen'])): ?>
                                    <div class="img-wrapper">
                                        <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($producto['imagen']); ?>" 
                                             class="img-thumbnail" 
                                             alt="Imagen principal"
                                             onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                        <div class="img-actions">
                                            <a href="editar-producto.php?id=<?php echo $producto_id; ?>" class="btn btn-sm btn-primary" title="Cambiar imagen principal">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">Este producto no tiene imagen principal</div>
                                    <a href="editar-producto.php?id=<?php echo $producto_id; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Agregar imagen principal
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6>Agregar imágenes adicionales</h6>
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <input class="form-control" type="file" id="nuevas_imagenes" name="nuevas_imagenes[]" multiple accept="image/*">
                                        <small class="text-muted">Puedes seleccionar múltiples imágenes (Máx. 5MB cada una)</small>
                                        <div id="preview_nuevas" class="preview-container mt-2"></div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-upload"></i> Subir imágenes
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h5>Imágenes adicionales (<?php echo count($imagenes_adicionales); ?>)</h5>
                        <?php if(!empty($imagenes_adicionales)): ?>
                            <div class="img-container">
                                <?php foreach($imagenes_adicionales as $imagen): ?>
                                    <div class="img-wrapper">
                                        <img src="<?php echo PRODUCT_IMAGES_PATH . htmlspecialchars($imagen); ?>" 
                                             class="img-thumbnail" 
                                             alt="Imagen adicional"
                                             onerror="this.src='<?php echo ASSETS_PATH; ?>img/placeholder.jpg'">
                                        <div class="img-actions">
                                            <a href="gestion-imagenes.php?id=<?php echo $producto_id; ?>&eliminar_imagen=<?php echo urlencode($imagen); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               title="Eliminar imagen"
                                               onclick="return confirm('¿Estás seguro de eliminar esta imagen?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">Este producto no tiene imágenes adicionales</div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar vista previa de nuevas imágenes antes de subir
        document.getElementById('nuevas_imagenes').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('preview_nuevas');
            previewContainer.innerHTML = '';
            
            const files = e.target.files;
            
            for(let i = 0; i < files.length; i++) {
                const file = files[i];
                if(file.size > 5 * 1024 * 1024) {
                    alert('La imagen "' + file.name + '" excede el tamaño máximo de 5MB');
                    continue;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'preview-thumbnail';
                    previewContainer.appendChild(img);
                }
                
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>