<?php
require_once '../check_session.php';
require_once '../config.php';

// Verify user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: /lms/login.php");
    exit();
}

$page_title = "Manage Publications";
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Category
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        if (!empty($category_name)) {
            $stmt = $conn->prepare("INSERT INTO publication_categories (name) VALUES (?)");
            $stmt->bind_param("s", $category_name);
            if ($stmt->execute()) {
                $success_message = "Category added successfully.";
            } else {
                $error_message = "Error adding category: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Category name is required.";
        }
    }
    // Delete Category
    elseif (isset($_POST['delete_category'])) {
        $category_id = intval($_POST['category_id']);
        
        // Check if publications exist in this category
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM publications WHERE category_id = ?");
        $check_stmt->bind_param("i", $category_id);
        $check_stmt->execute();
        $count = $check_stmt->get_result()->fetch_row()[0];
        $check_stmt->close();
        
        if ($count > 0) {
             $error_message = "Cannot delete category containing publications. Please delete the publications first.";
        } else {
            $stmt = $conn->prepare("DELETE FROM publication_categories WHERE id = ?");
            $stmt->bind_param("i", $category_id);
            if ($stmt->execute()) {
                $success_message = "Category deleted successfully.";
            } else {
                $error_message = "Error deleting category: " . $conn->error;
            }
            $stmt->close();
        }
    }
    // Add Publication
    elseif (isset($_POST['add_publication'])) {
        $category_id = intval($_POST['category_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $discount = floatval($_POST['discount']);
        
        // Handle file upload
        $image_path = '';
        if (isset($_FILES['publication_image']) && $_FILES['publication_image']['error'] == 0) {
            $upload_dir = '../uploads/publications/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['publication_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = array('jpg', 'jpeg', 'png', 'webp');
            
            if (in_array($file_extension, $allowed_extensions)) {
                $unique_filename = uniqid('pub_') . '.' . $file_extension;
                $target_file = $upload_dir . $unique_filename;
                
                if (move_uploaded_file($_FILES['publication_image']['tmp_name'], $target_file)) {
                    $image_path = 'uploads/publications/' . $unique_filename;
                } else {
                    $error_message = "Failed to upload image.";
                }
            } else {
                $error_message = "Invalid image format. Allowed formats: JPG, JPEG, PNG, WEBP.";
            }
        }
        
        if (empty($error_message)) {
            $stmt = $conn->prepare("INSERT INTO publications (category_id, title, description, price, discount, image_path) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdds", $category_id, $title, $description, $price, $discount, $image_path);
            
            if ($stmt->execute()) {
                $success_message = "Publication added successfully.";
            } else {
                $error_message = "Error adding publication: " . $conn->error;
            }
            $stmt->close();
        }
    }
    // Delete Publication
    elseif (isset($_POST['delete_publication'])) {
        $publication_id = intval($_POST['publication_id']);
        
        // Get image path to delete file
        $stmt = $conn->prepare("SELECT image_path FROM publications WHERE id = ?");
        $stmt->bind_param("i", $publication_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $image_path = $row['image_path'];
            if (!empty($image_path) && file_exists('../' . $image_path)) {
                unlink('../' . $image_path);
            }
        }
        $stmt->close();
        
        // Delete record
        $stmt = $conn->prepare("DELETE FROM publications WHERE id = ?");
        $stmt->bind_param("i", $publication_id);
        if ($stmt->execute()) {
            $success_message = "Publication deleted successfully.";
        } else {
            $error_message = "Error deleting publication: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch Categories
$categories = $conn->query("SELECT * FROM publication_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch Publications
$publications_query = "
    SELECT p.*, c.name as category_name 
    FROM publications p 
    LEFT JOIN publication_categories c ON p.category_id = c.id 
    ORDER BY p.created_at DESC
";
$publications = $conn->query($publications_query)->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Publications - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        
        <div class="px-4 py-6 sm:px-0">
             <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Manage Publications</h1>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Category Management Block -->
                <div class="bg-white p-6 rounded-lg shadow-md h-fit">
                    <h2 class="text-lg font-bold mb-4 border-b pb-2">Add Category</h2>
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                            <input type="text" name="category_name" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 border">
                        </div>
                        <button type="submit" name="add_category" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700 transition">Add Category</button>
                    </form>

                    <div class="mt-8">
                        <h2 class="text-lg font-bold mb-4 border-b pb-2">Existing Categories</h2>
                        <ul class="divide-y divide-gray-200 border rounded-md max-h-60 overflow-y-auto">
                            <?php foreach ($categories as $cat): ?>
                            <li class="p-3 flex justify-between items-center hover:bg-gray-50">
                                <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($cat['name']); ?></span>
                                <form method="POST" action="" onsubmit="return confirm('Delete this category?');" class="inline">
                                    <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                    <button type="submit" name="delete_category" class="text-red-600 hover:text-red-900 ml-2">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </form>
                            </li>
                            <?php endforeach; ?>
                            <?php if (empty($categories)): ?>
                            <li class="p-4 text-sm text-gray-500 text-center">No categories found.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- Add Publication Form -->
                <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-md h-fit">
                    <h2 class="text-lg font-bold mb-4 border-b pb-2">Add New Publication</h2>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                <input type="text" name="title" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 border">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                <select name="category_id" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 border">
                                    <option value="">-- Choose Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4 md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea name="description" rows="3" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 border"></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Price (LKR)</label>
                                <input type="number" step="0.01" min="0" name="price" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 border">
                            </div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Discount amount (LKR)</label>
                                <input type="number" step="0.01" min="0" value="0" name="discount" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 border">
                            </div>
                            <div class="mb-4 md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Publication Image</label>
                                <input type="file" name="publication_image" accept="image/jpeg, image/png, image/webp" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-2 border">
                            </div>
                        </div>
                        <button type="submit" name="add_publication" class="w-full mt-4 bg-green-600 text-white py-2 rounded-md hover:bg-green-700 transition">Publish</button>
                    </form>
                </div>
            </div>

            <!-- Publications List -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-lg font-bold mb-4 border-b pb-2">All Publications</h2>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title & Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pricing</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($publications as $pub): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (!empty($pub['image_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($pub['image_path']); ?>" alt="Cover" class="w-16 h-20 object-cover rounded shadow-sm border">
                                    <?php else: ?>
                                        <div class="w-16 h-20 flex items-center justify-center bg-gray-100 rounded shadow-sm border">
                                            <i class="fas fa-book text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($pub['title']); ?></div>
                                    <div class="text-xs text-blue-600 mt-1"><i class="fas fa-tag mr-1"></i> <?php echo htmlspecialchars($pub['category_name']); ?></div>
                                    <?php if (!empty($pub['description'])): ?>
                                        <div class="text-xs text-gray-500 mt-1 truncate w-48"><?php echo htmlspecialchars($pub['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($pub['discount'] > 0): ?>
                                        <div class="text-sm font-bold text-green-600">LKR <?php echo number_format($pub['price'] - $pub['discount'], 2); ?></div>
                                        <div class="text-xs text-gray-500 line-through">LKR <?php echo number_format($pub['price'], 2); ?></div>
                                    <?php else: ?>
                                        <div class="text-sm font-bold text-gray-900">LKR <?php echo number_format($pub['price'], 2); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this publication?');" class="inline">
                                        <input type="hidden" name="publication_id" value="<?php echo $pub['id']; ?>">
                                        <button type="submit" name="delete_publication" class="text-red-600 hover:text-red-900 bg-red-50 p-2 rounded-full hover:bg-red-100">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($publications)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500 font-medium">No publications added yet.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
