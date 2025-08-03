<?php
require_once '../config.php';

// Función para limpiar datos de entrada
function limpiarDatos($dato) {
    $dato = trim($dato);
    $dato = stripslashes($dato);
    $dato = htmlspecialchars($dato);
    return $dato;
}


// Verificar si el usuario es admin
if(!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit;
} elseif($_SESSION['usuario_rol'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$error = '';
$success = '';

// Definir extensiones permitidas para imágenes
$extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];

// Obtener categorías disponibles
$categorias = [];
try {
    $stmt = $conn->query("SELECT categoria_id, nombre FROM categorias ORDER BY nombre");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = 'Error al obtener las categorías: ' . $e->getMessage();
}

// Obtener tallas disponibles agrupadas por categoría de talla
$tallas_por_categoria = [];
try {
    $stmt = $conn->query("SELECT id, nombre, categoria_talla FROM tallas ORDER BY categoria_talla, orden");
    $tallas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organizar tallas por categoría
    foreach($tallas as $talla) {
        $tallas_por_categoria[$talla['categoria_talla']][] = $talla;
    }
} catch(PDOException $e) {
    $error = 'Error al obtener las tallas disponibles: ' . $e->getMessage();
}

// Procesar creación de producto
if($_SERVER['REQUEST_METHOD'] == 'POST' && !$error) {
    $nombre = limpiarDatos($_POST['nombre']);
    $descripcion = limpiarDatos($_POST['descripcion']);
    $precio_normal = limpiarDatos($_POST['precio_normal']);
    $precio_descuento = limpiarDatos($_POST['precio_descuento']);
    $categoria_id = limpiarDatos($_POST['categoria_id']);
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    $sku = limpiarDatos($_POST['sku'] ?? '');
    
    // Obtener tallas seleccionadas
    $tallas_seleccionadas = [];
    if(isset($_POST['tallas_seleccionadas']) && is_array($_POST['tallas_seleccionadas'])) {
        foreach($_POST['tallas_seleccionadas'] as $talla_id) {
            $talla_id = (int)$talla_id;
            $stock = isset($_POST['stock_talla'][$talla_id]) ? (int)$_POST['stock_talla'][$talla_id] : 0;
            
            if($stock > 0) {
                $tallas_seleccionadas[$talla_id] = $stock;
            }
        }
    }
    
    // Validaciones
    if(empty($nombre) || empty($precio_normal)) {
        $error = 'Nombre y precio son obligatorios';
    } elseif(!is_numeric($precio_normal) || $precio_normal <= 0) {
        $error = 'El precio debe ser un número positivo';
    } elseif($precio_descuento && (!is_numeric($precio_descuento) || $precio_descuento <= 0)) {
        $error = 'El precio de descuento debe ser un número positivo';
    } elseif(empty($tallas_seleccionadas)) {
        $error = 'Debes seleccionar al menos una talla y asignarle stock';
    } else {
        // Procesar imagen principal
        $imagen = '';
        if(isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $nombre_archivo = $_FILES['imagen']['name'];
            $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
            
            if(in_array($extension, $extensiones_permitidas)) {
                $nombre_unico = uniqid('product_', true) . '.' . $extension;
                $ruta_destino = '../assets/img/productos/' . $nombre_unico;
                
                if(move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
                    $imagen = $nombre_unico;
                } else {
                    $error = 'Error al subir la imagen principal';
                }
            } else {
                $error = 'Formato de imagen no permitido. Use JPG, JPEG, PNG o GIF.';
            }
        }
        
        // Procesar imágenes adicionales
        $imagenes_adicionales = [];
        if(!$error && isset($_FILES['imagenes_adicionales']) && is_array($_FILES['imagenes_adicionales']['name'])) {
            foreach($_FILES['imagenes_adicionales']['name'] as $key => $nombre_archivo) {
                if($_FILES['imagenes_adicionales']['error'][$key] === UPLOAD_ERR_OK) {
                    $extension = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
                    
                    if(in_array($extension, $extensiones_permitidas)) {
                        $nombre_unico = uniqid('product_extra_', true) . '.' . $extension;
                        $ruta_destino = '../assets/img/productos/' . $nombre_unico;
                        
                        if(move_uploaded_file($_FILES['imagenes_adicionales']['tmp_name'][$key], $ruta_destino)) {
                            $imagenes_adicionales[] = $nombre_unico;
                        } else {
                            $error = 'Error al subir una de las imágenes adicionales';
                            break;
                        }
                    } else {
                        $error = 'Formato de imagen no permitido en una de las imágenes adicionales. Use JPG, JPEG, PNG o GIF.';
                        break;
                    }
                }
            }
        }
        
        if(!$error) {
            // Convertir array de imágenes adicionales a JSON para guardar en la base de datos
            $imagenes_adicionales_json = !empty($imagenes_adicionales) ? json_encode($imagenes_adicionales) : null;
            
            try {
                $conn->beginTransaction();
                
                // Insertar nuevo producto
                $stmt = $conn->prepare("INSERT INTO productos 
                    (nombre, descripcion, precio_normal, precio_descuento, categoria_id, imagen, imagenes_adicionales, destacado, sku) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $nombre, 
                    $descripcion, 
                    $precio_normal, 
                    $precio_descuento ?: null, 
                    $categoria_id, 
                    $imagen ?: null,
                    $imagenes_adicionales_json,
                    $destacado,
                    $sku ?: null
                ]);
                
                $producto_id = $conn->lastInsertId();
                
                // Insertar tallas y stocks
                foreach($tallas_seleccionadas as $talla_id => $stock) {
                    $stmt = $conn->prepare("INSERT INTO producto_tallas (producto_id, talla_id, stock) VALUES (?, ?, ?)");
                    $stmt->execute([$producto_id, $talla_id, $stock]);
                }
                
                $conn->commit();
                $_SESSION['mensaje'] = 'Producto creado correctamente';
                header("Location: productos.php");
                exit;
            } catch(PDOException $e) {
                $conn->rollBack();
                $error = 'Error al crear el producto: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Producto - Kalo's Style</title>
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
        
        .preview-img {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            display: none;
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
        
        .talla-item {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        
        .talla-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .talla-type-badge {
            font-size: 0.75rem;
            padding: 0.25em 0.4em;
            border-radius: 0.25rem;
        }
        
        .badge-camisa {
            background-color: #0d6efd;
            color: white;
        }
        
        .badge-pantalon {
            background-color: #198754;
            color: white;
        }
        
        .badge-zapato {
            background-color: #6f42c1;
            color: white;
        }
        
        .talla-select-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
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
                    <h1 class="h2">Crear Nuevo Producto</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="productos.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                
                <?php if($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['mensaje'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nombre" class="form-label">Nombre del producto *</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="descripcion" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="categoria_id" class="form-label">Categoría *</label>
                                        <select class="form-select" id="categoria_id" name="categoria_id" required>
                                            <option value="">Seleccione una categoría</option>
                                            <?php foreach($categorias as $categoria): ?>
                                                <option value="<?php echo $categoria['categoria_id']; ?>">
                                                    <?php echo htmlspecialchars($categoria['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="sku" class="form-label">SKU (Código de producto)</label>
                                        <input type="text" class="form-control" id="sku" name="sku">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="precio_normal" class="form-label">Precio normal *</label>
                                        <input type="number" step="0.01" class="form-control" id="precio_normal" name="precio_normal" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="precio_descuento" class="form-label">Precio con descuento (opcional)</label>
                                        <input type="number" step="0.01" class="form-control" id="precio_descuento" name="precio_descuento">
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="destacado" name="destacado">
                                            <label class="form-check-label" for="destacado">
                                                Producto destacado
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sección de tallas -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h5>Selección de Tallas</h5>
                                    <p>Selecciona las tallas disponibles para este producto y asigna el stock correspondiente:</p>
                                    
                                    <div class="talla-select-container">
                                        <?php foreach($tallas_por_categoria as $categoria_talla => $tallas): ?>
                                            <h6 class="mt-3">
                                                <?php echo ucfirst($categoria_talla); ?> 
                                                <span class="badge <?php echo 'badge-' . $categoria_talla; ?>">
                                                    <?php echo strtoupper($categoria_talla); ?>
                                                </span>
                                            </h6>
                                            <div class="row">
                                                <?php foreach($tallas as $talla): ?>
                                                    <div class="col-md-4 mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input toggle-talla" type="checkbox" 
                                                                   id="talla_<?php echo $talla['id']; ?>" 
                                                                   name="tallas_seleccionadas[]" 
                                                                   value="<?php echo $talla['id']; ?>"
                                                                   data-talla-id="<?php echo $talla['id']; ?>">
                                                            <label class="form-check-label" for="talla_<?php echo $talla['id']; ?>">
                                                                <?php echo htmlspecialchars($talla['nombre']); ?>
                                                            </label>
                                                        </div>
                                                        <div class="talla-stock mt-2" id="stock_talla_<?php echo $talla['id']; ?>" style="display: none;">
                                                            <label for="stock_<?php echo $talla['id']; ?>" class="form-label">Stock:</label>
                                                            <input type="number" class="form-control stock-input" 
                                                                   id="stock_<?php echo $talla['id']; ?>" 
                                                                   name="stock_talla[<?php echo $talla['id']; ?>]" 
                                                                   min="0" value="1">
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="imagen" class="form-label">Imagen principal del producto</label>
                                        <input class="form-control" type="file" id="imagen" name="imagen" accept="image/*">
                                        <img id="preview_principal" class="preview-img" src="#" alt="Vista previa de la imagen principal">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="imagenes_adicionales" class="form-label">Imágenes adicionales (opcional)</label>
                                        <input class="form-control" type="file" id="imagenes_adicionales" name="imagenes_adicionales[]" multiple accept="image/*">
                                        <small class="text-muted">Puedes seleccionar múltiples imágenes manteniendo presionada la tecla Ctrl o Shift.</small>
                                        <div id="preview_adicionales" class="preview-container"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Crear Producto</button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar vista previa de la imagen principal
        document.getElementById('imagen').addEventListener('change', function(e) {
            const preview = document.getElementById('preview_principal');
            const file = e.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            
            if(file) {
                reader.readAsDataURL(file);
            } else {
                preview.src = '#';
                preview.style.display = 'none';
            }
        });
        
        // Mostrar vista previa de imágenes adicionales
        document.getElementById('imagenes_adicionales').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('preview_adicionales');
            previewContainer.innerHTML = '';
            
            const files = e.target.files;
            
            for(let i = 0; i < files.length; i++) {
                const file = files[i];
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
        
        // Habilitar/deshabilitar campo de stock al seleccionar talla
        document.querySelectorAll('.toggle-talla').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const tallaId = this.getAttribute('data-talla-id');
                const stockInput = document.getElementById(`stock_${tallaId}`);
                
                if(this.checked) {
                    stockInput.disabled = false;
                    stockInput.style.display = 'block';
                    if(stockInput.value == '0') {
                        stockInput.value = '1'; // Valor por defecto
                    }
                } else {
                    stockInput.disabled = true;
                    stockInput.style.display = 'none';
                    stockInput.value = '0';
                }
            });
        });
        
        // Validar que al menos una talla tenga stock
        document.querySelector('form').addEventListener('submit', function(e) {
            const tallasSeleccionadas = document.querySelectorAll('.toggle-talla:checked');
            if(tallasSeleccionadas.length === 0) {
                e.preventDefault();
                alert('Debes seleccionar al menos una talla y asignarle stock');
                return;
            }
            
            // Validar que todas las tallas seleccionadas tengan stock > 0
            let alMenosUnaConStock = false;
            tallasSeleccionadas.forEach(checkbox => {
                const tallaId = checkbox.getAttribute('data-talla-id');
                const stock = parseInt(document.getElementById(`stock_${tallaId}`).value);
                if(stock > 0) {
                    alMenosUnaConStock = true;
                }
            });
            
            if(!alMenosUnaConStock) {
                e.preventDefault();
                alert('Debes asignar una cantidad mayor a cero al menos a una talla');
            }
        });
    </script>
</body>
</html>