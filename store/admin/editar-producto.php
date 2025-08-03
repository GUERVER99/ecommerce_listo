<?php
require_once '../config.php';

// Verificar permisos de administrador
if(!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Validar ID del producto
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: productos.php");
    exit;
}

$producto_id = (int)$_GET['id'];

// Obtener datos del producto con JOIN para categoría
$stmt = $conn->prepare("SELECT p.*, c.nombre as categoria_nombre 
                       FROM productos p 
                       LEFT JOIN categorias c ON p.categoria_id = c.categoria_id 
                       WHERE p.id = ?");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$producto) {
    header("Location: productos.php");
    exit;
}

// Establecer valor por defecto para 'activo' si no existe
if(!array_key_exists('activo', $producto)) {
    $producto['activo'] = 1; // 1 = activo por defecto
}

// Obtener imágenes adicionales
$imagenes_adicionales = [];
if (!empty($producto['imagenes_adicionales'])) {
    $imagenes_adicionales = json_decode($producto['imagenes_adicionales'], true);
}

// Determinar tipo de talla según categoría
$tipo_talla = 'camisa'; // Valor por defecto
if(!empty($producto['categoria_nombre'])) {
    $categoria_nombre = strtolower($producto['categoria_nombre']);
    
    if(strpos($categoria_nombre, 'pantalon') !== false) {
        $tipo_talla = 'pantalon';
    } elseif(strpos($categoria_nombre, 'zapato') !== false || strpos($categoria_nombre, 'calzado') !== false) {
        $tipo_talla = 'zapato';
    }
}

// Obtener tallas disponibles para esta categoría
$stmt_tallas = $conn->prepare("SELECT * FROM tallas WHERE categoria_talla = ? ORDER BY orden");
$stmt_tallas->execute([$tipo_talla]);
$todas_tallas = $stmt_tallas->fetchAll(PDO::FETCH_ASSOC);

// Obtener tallas asignadas al producto
$stmt_producto_tallas = $conn->prepare("SELECT talla_id, stock FROM producto_tallas WHERE producto_id = ?");
$stmt_producto_tallas->execute([$producto_id]);
$tallas_producto = $stmt_producto_tallas->fetchAll(PDO::FETCH_KEY_PAIR);

// Obtener categorías para select
$categorias = $conn->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar y limpiar datos
    $nombre = htmlspecialchars(trim($_POST['nombre']));
    $descripcion = htmlspecialchars(trim($_POST['descripcion']));
    $precio_normal = (float)$_POST['precio_normal'];
    $precio_descuento = !empty($_POST['precio_descuento']) ? (float)$_POST['precio_descuento'] : null;
    $categoria_id = (int)$_POST['categoria_id'];
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $imagen_actual = $producto['imagen'];
    
    // Manejo automático de fechas para descuentos
    if($precio_descuento) {
        $descuento_inicio = date('Y-m-d H:i:s'); // Fecha/hora actual
        $descuento_fin = date('Y-m-d H:i:s', strtotime('+3 days')); // 3 días después
    } else {
        $descuento_inicio = null;
        $descuento_fin = null;
    }
    
    // Validaciones
    $error = '';
    if(empty($nombre) || empty($precio_normal) || empty($categoria_id)) {
        $error = 'Nombre, precio y categoría son obligatorios';
    } elseif($precio_descuento && $precio_descuento >= $precio_normal) {
        $error = 'El precio con descuento debe ser menor al precio normal';
    }
    
    // Procesar imagen principal si no hay errores
    if(!$error && isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif'];
        
        if(in_array($extension, $extensiones_permitidas)) {
            // Eliminar imagen anterior si existe
            if($imagen_actual && file_exists("../assets/img/productos/$imagen_actual")) {
                unlink("../assets/img/productos/$imagen_actual");
            }
            
            // Generar nombre único y mover archivo
            $nombre_unico = uniqid('product_', true) . '.' . $extension;
            $ruta_destino = '../assets/img/productos/' . $nombre_unico;
            
            if(move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
                $imagen_actual = $nombre_unico;
            } else {
                $error = 'Error al subir la imagen principal';
            }
        } else {
            $error = 'Formato de imagen no permitido. Use JPG, JPEG, PNG o GIF.';
        }
    }
    
    // Procesar imágenes adicionales
    $nuevas_imagenes_adicionales = [];
    if(!$error) {
        // Mantener imágenes existentes si no se borran
        if(isset($_POST['imagen_adicional_existente'])) {
            foreach($_POST['imagen_adicional_existente'] as $imagen) {
                if(in_array($imagen, $imagenes_adicionales)) {
                    $nuevas_imagenes_adicionales[] = $imagen;
                }
            }
        }
        
        // Procesar nuevas imágenes adicionales
        if(isset($_FILES['nuevas_imagenes_adicionales'])) {
            foreach($_FILES['nuevas_imagenes_adicionales']['tmp_name'] as $key => $tmp_name) {
                if($_FILES['nuevas_imagenes_adicionales']['error'][$key] === UPLOAD_ERR_OK) {
                    $extension = strtolower(pathinfo($_FILES['nuevas_imagenes_adicionales']['name'][$key], PATHINFO_EXTENSION));
                    
                    if(in_array($extension, $extensiones_permitidas)) {
                        $nombre_unico = uniqid('product_extra_', true) . '.' . $extension;
                        $ruta_destino = '../assets/img/productos/' . $nombre_unico;
                        
                        if(move_uploaded_file($tmp_name, $ruta_destino)) {
                            $nuevas_imagenes_adicionales[] = $nombre_unico;
                        }
                    }
                }
            }
        }
    }
    
    // Procesar tallas seleccionadas
    $tallas_seleccionadas = [];
    if(!$error && isset($_POST['tallas'])) {
        foreach($_POST['tallas'] as $talla_id => $stock) {
            $talla_id = (int)$talla_id;
            $stock = (int)$stock;
            
            if($stock > 0) {
                $tallas_seleccionadas[$talla_id] = $stock;
            }
        }
        
        if(empty($tallas_seleccionadas)) {
            $error = 'Debe asignar stock a al menos una talla';
        }
    }
    
    // Actualizar en base de datos si no hay errores
    if(!$error) {
        $conn->beginTransaction();
        
        try {
            // Actualizar producto
            $stmt = $conn->prepare("UPDATE productos SET 
                                  nombre = ?, 
                                  descripcion = ?, 
                                  precio_normal = ?, 
                                  precio_descuento = ?, 
                                  categoria_id = ?, 
                                  destacado = ?, 
                                  activo = ?,
                                  descuento_inicio = ?,
                                  descuento_fin = ?,
                                  imagenes_adicionales = ?,
                                  imagen = ?
                                  WHERE id = ?");
            
            $stmt->execute([
                $nombre, 
                $descripcion, 
                $precio_normal, 
                $precio_descuento, 
                $categoria_id, 
                $destacado, 
                $activo,
                $descuento_inicio,
                $descuento_fin,
                json_encode($nuevas_imagenes_adicionales),
                $imagen_actual,
                $producto_id
            ]);
            
            // Actualizar tallas y stock
            $stmt = $conn->prepare("DELETE FROM producto_tallas WHERE producto_id = ?");
            $stmt->execute([$producto_id]);
            
            foreach($tallas_seleccionadas as $talla_id => $stock) {
                $stmt = $conn->prepare("INSERT INTO producto_tallas (producto_id, talla_id, stock) VALUES (?, ?, ?)");
                $stmt->execute([$producto_id, $talla_id, $stock]);
            }
            
            $conn->commit();
            $_SESSION['mensaje'] = 'Producto actualizado correctamente';
            header("Location: productos.php");
            exit;
            
        } catch(PDOException $e) {
            $conn->rollBack();
            $error = 'Error al actualizar el producto: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Khalo's Style</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #212529;
            color: white;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .product-gallery {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .main-product-image {
            width: 100%;
            height: 300px;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .thumbnail-container {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .product-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .product-thumbnail:hover,
        .product-thumbnail.active {
            border-color: #000;
        }
        
        .size-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
        }
        
        .size-option {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .size-option.selected {
            background-color: #000;
            color: white;
            border-color: #000;
        }
        
        .discount-banner {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
        }
        
        .img-preview-container {
            position: relative;
            display: inline-block;
        }
        
        .img-remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .main-product-image {
                height: 250px;
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
                    <h1 class="h2">Editar Producto</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="productos.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                
                <?php if(isset($error) && $error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <!-- Información básica -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Información básica</h5>
                                    
                                    <div class="mb-3">
                                        <label for="nombre" class="form-label">Nombre del producto *</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="descripcion" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="categoria_id" class="form-label">Categoría *</label>
                                        <select class="form-select" id="categoria_id" name="categoria_id" required>
                                            <option value="">Seleccione una categoría</option>
                                            <?php foreach($categorias as $categoria): ?>
                                                <option value="<?php echo $categoria['categoria_id']; ?>" <?php echo $categoria['categoria_id'] == $producto['categoria_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($categoria['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="precio_normal" class="form-label">Precio normal *</label>
                                                <input type="number" step="0.01" min="0" class="form-control" id="precio_normal" name="precio_normal" value="<?php echo number_format($producto['precio_normal'], 2, '.', ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="precio_descuento" class="form-label">Precio con descuento</label>
                                                <input type="number" step="0.01" min="0" class="form-control" id="precio_descuento" name="precio_descuento" value="<?php echo $producto['precio_descuento'] ? number_format($producto['precio_descuento'], 2, '.', '') : ''; ?>">
                                                <small class="text-muted">Al activar descuento, se aplicará automáticamente por 3 días</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if($producto['precio_descuento'] && $producto['descuento_fin']): ?>
                                    <div class="discount-banner mb-3">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Inicio descuento</label>
                                                    <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i', strtotime($producto['descuento_inicio'])); ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Fin descuento</label>
                                                    <input type="text" class="form-control" value="<?php echo date('d/m/Y H:i', strtotime($producto['descuento_fin'])); ?>" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="destacado" name="destacado" <?php echo $producto['destacado'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="destacado">Producto destacado</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="activo" name="activo" <?php echo $producto['activo'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="activo">Producto activo</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Gestión de tallas -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Tallas y Stock</h5>
                                    <p class="text-muted">Tipo de talla: <?php echo ucfirst($tipo_talla); ?></p>
                                    
                                    <div class="size-grid mb-3">
                                        <?php foreach($todas_tallas as $talla): ?>
                                            <div class="size-option">
                                                <label for="talla_<?php echo $talla['id']; ?>" class="d-block text-center mb-1">
                                                    <?php echo htmlspecialchars($talla['nombre']); ?>
                                                </label>
                                                <input type="number" 
                                                       id="talla_<?php echo $talla['id']; ?>" 
                                                       name="tallas[<?php echo $talla['id']; ?>]" 
                                                       class="form-control form-control-sm" 
                                                       min="0" 
                                                       value="<?php echo $tallas_producto[$talla['id']] ?? 0; ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <!-- Imágenes del producto -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Imágenes del producto</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Imagen principal</label>
                                        <?php if($producto['imagen']): ?>
                                            <img src="../assets/img/productos/<?php echo htmlspecialchars($producto['imagen']); ?>" 
                                                 class="main-product-image mb-2" 
                                                 alt="Imagen principal" 
                                                 id="imagenPrincipalPreview">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center" style="height: 300px;">
                                                <span class="text-muted">Sin imagen principal</span>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Imágenes adicionales</label>
                                        <div class="thumbnail-container mb-3">
                                            <?php foreach($imagenes_adicionales as $index => $imagen): ?>
                                                <div class="img-preview-container">
                                                    <img src="../assets/img/productos/<?php echo htmlspecialchars($imagen); ?>" 
                                                         class="product-thumbnail" 
                                                         alt="Imagen adicional <?php echo $index + 1; ?>">
                                                    <input type="hidden" name="imagen_adicional_existente[]" value="<?php echo htmlspecialchars($imagen); ?>">
                                                    <button type="button" class="img-remove-btn" onclick="removeImage(this)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <input type="file" class="form-control" id="nuevas_imagenes_adicionales" name="nuevas_imagenes_adicionales[]" multiple accept="image/*">
                                        <small class="text-muted">Puedes seleccionar múltiples imágenes</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="productos.php" class="btn btn-secondary me-md-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Vista previa de imagen principal
        document.getElementById('imagen').addEventListener('change', function(e) {
            const preview = document.getElementById('imagenPrincipalPreview');
            const file = e.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                if(!preview) {
                    // Crear elemento de vista previa si no existe
                    const container = document.querySelector('.main-product-image').parentElement;
                    const newPreview = document.createElement('img');
                    newPreview.id = 'imagenPrincipalPreview';
                    newPreview.className = 'main-product-image mb-2';
                    newPreview.src = e.target.result;
                    container.insertBefore(newPreview, document.getElementById('imagen'));
                } else {
                    preview.src = e.target.result;
                }
            }
            
            if(file) {
                reader.readAsDataURL(file);
            }
        });
        
        // Eliminar imágenes adicionales
        function removeImage(button) {
            if(confirm('¿Estás seguro de que deseas eliminar esta imagen?')) {
                button.parentElement.remove();
            }
        }
        
        // Vista previa para nuevas imágenes adicionales
        document.getElementById('nuevas_imagenes_adicionales').addEventListener('change', function(e) {
            const container = document.querySelector('.thumbnail-container');
            
            for(let i = 0; i < this.files.length; i++) {
                const file = this.files[i];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'img-preview-container';
                    
                    const img = document.createElement('img');
                    img.className = 'product-thumbnail';
                    img.src = e.target.result;
                    
                    div.appendChild(img);
                    container.appendChild(div);
                }
                
                reader.readAsDataURL(file);
            }
        });
        
        // Validación de formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const precioDescuento = parseFloat(document.getElementById('precio_descuento').value);
            const precioNormal = parseFloat(document.getElementById('precio_normal').value);
            
            if(precioDescuento && precioDescuento >= precioNormal) {
                e.preventDefault();
                alert('El precio con descuento debe ser menor al precio normal');
                document.getElementById('precio_descuento').focus();
            }
            
            const tallasInputs = document.querySelectorAll('input[name^="tallas["]');
            let hasStock = false;
            
            tallasInputs.forEach(input => {
                if(parseInt(input.value) > 0) {
                    hasStock = true;
                }
            });
            
            if(!hasStock) {
                e.preventDefault();
                alert('Debes asignar stock a al menos una talla');
            }
        });
    </script>
</body>
</html>