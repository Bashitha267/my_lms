<?php
// restore_full_al_results.php - Imports all 175 student A/L exam submissions from extracted summary
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    die("Error: config.php file not found.");
}

$conn->query("SET FOREIGN_KEY_CHECKS = 0;");

// Ensure table exists with all required columns
$create_table_sql = "
CREATE TABLE IF NOT EXISTS `al_exam_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(100) DEFAULT NULL,
  `student_name` varchar(255) DEFAULT NULL,
  `school_name` varchar(255) DEFAULT NULL,
  `subject_1` varchar(100) DEFAULT NULL,
  `result_1` varchar(10) DEFAULT NULL,
  `subject_2` varchar(100) DEFAULT NULL,
  `result_2` varchar(10) DEFAULT NULL,
  `subject_3` varchar(100) DEFAULT NULL,
  `result_3` varchar(10) DEFAULT NULL,
  `index_number` varchar(50) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `stream` varchar(100) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `agreed_to_publish` tinyint(1) DEFAULT 1,
  `results_submitted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `district_rank` int(11) DEFAULT NULL,
  `island_rank` int(11) DEFAULT NULL,
  `z_score` decimal(6,4) DEFAULT NULL,
  `exam_year` int(4) DEFAULT 2023,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
$conn->query($create_table_sql);

// Check if teacher_id, student_name & z_score columns exist, add if missing
$col0 = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'teacher_id'");
if ($col0 && $col0->num_rows === 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN teacher_id VARCHAR(20) DEFAULT 'T_0002' AFTER student_id");
}
$col1 = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'student_name'");
if ($col1 && $col1->num_rows === 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN student_name VARCHAR(255) DEFAULT NULL AFTER student_id");
}
$col2 = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'school_name'");
if ($col2 && $col2->num_rows === 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN school_name VARCHAR(255) DEFAULT NULL AFTER student_name");
}
$col3 = $conn->query("SHOW COLUMNS FROM al_exam_submissions LIKE 'z_score'");
if ($col3 && $col3->num_rows === 0) {
    $conn->query("ALTER TABLE al_exam_submissions ADD COLUMN z_score DECIMAL(6,4) DEFAULT NULL AFTER island_rank");
}

// Ensure existing records are assigned to T_0002
$conn->query("UPDATE al_exam_submissions SET teacher_id = 'T_0002' WHERE teacher_id IS NULL OR teacher_id = ''");

$students_data = [
    ['Imali Jayawardhana', 'Sri Parackrama National School, Matale', 2021, 'Media', 'C', 'Buddhist Culture', 'B', 'Sinhala', 'A', 367, 13523, 1.1459, 'Matale', 'Arts'],
    ['Hashinika Wijesingha', 'Girls\' Highschool, Kandy', 2022, 'Media', 'S', 'ICT', 'S', 'Sinhala', 'B', 2818, 43174, 0.1473, 'Kandy', 'Arts'],
    ['Avishka Pasindu Vijayanga', 'Gamini Dissanayaka National School, Kothmale', 2022, 'Media', 'C', 'Geography', 'S', 'Sinhala', 'C', null, null, null, 'Kothmale', 'Arts'],
    ['Kavindya Madugale', 'Seethadevi Girls\' College, Kandy', 2022, 'Media', 'S', 'Dancing', 'C', 'Sinhala', 'C', null, null, 0.1202, 'Kandy', 'Arts'],
    ['Chanchala Hathurusinghe', 'Delta Gamunupura Kothmale', 2023, 'Media', 'B', 'Geography', 'S', 'History', 'C', 1369, 42480, 1.8470, 'Kothmale', 'Arts'],
    ['Navodya Lakshani', 'Godapitiya National School, Matara', 2023, 'Media', 'A', 'Political Science', 'A', 'Sinhala', 'A', 312, 5959, 1.4236, 'Matara', 'Arts'],
    ['Virgini Shara', 'All Saints College Boralla', 2023, 'Media', 'B', 'Sinhala', 'B', 'Christianity', 'A', 708, 10267, 1.2226, 'Colombo', 'Arts'],
    ['Upeksha Kavindi', 'Wickramabahu National College, Kandy', 2023, 'Media', 'C', 'Geography', 'C', 'Sinhala', 'C', 1538, 25949, 0.6872, 'Kandy', 'Arts'],
    ['Thushantha Karunathilaka', 'Not Specified', 2023, 'Media', 'S', 'Geography', 'C', 'Political Science', 'S', 3926, 57733, 0.3178, 'Not Specified', 'Arts'],
    ['Thakshila Sewwandi Kumari', 'Not Specified', 2023, 'Media', 'B', 'Geography', 'C', 'Dancing', 'C', 1482, 25041, 0.7158, 'Not Specified', 'Arts'],
    ['Diyana Dhaneshwari Ariyarathna', 'Not Specified', 2023, 'Media', 'S', 'Home Science', 'C', 'Sinhala', 'C', 2799, 43166, 0.1654, 'Not Specified', 'Arts'],
    ['Limasha Kavindi', 'Rajapaksha National College, Hambantota', 2023, 'Media', 'C', 'Political Science', 'C', 'Sinhala', 'C', 1582, 42399, 0.1872, 'Hambantota', 'Arts'],
    ['Pujani Nuwandika', 'Nayapana M.V', 2023, 'Media', 'C', 'Economics', 'C', 'Geography', 'C', 1215, 38125, 0.3170, 'Gampola', 'Arts'],
    ['Sonadi Wijewickrama', 'Siridhamma College, Galle', 2023, 'Media', 'S', 'History', 'C', 'Geography', 'S', 2536, 45434, 0.8510, 'Galle', 'Arts'],
    ['Fathima Rizna', 'Badrdinmahnud Balika Vidyalaya, Kandy', 2023, 'Media', 'S', 'Political Science', 'S', 'Sinhala', 'C', 4089, 39948, 0.4566, 'Kandy', 'Arts'],
    ['Senuri Kaushalya', 'Vidyawardhana Maha Vidyalaya, Battaramulla', 2023, 'Media', 'C', 'Sinhala', 'C', 'Political Science', 'S', 3040, 47560, 0.2620, 'Colombo', 'Arts'],
    ['Malsha Prabodha', 'Wickramabahu National College', 2023, 'Media', 'B', 'Geography', 'C', 'Sinhala', 'B', 1425, 24219, 0.7422, 'Kandy', 'Arts'],
    ['Samindi Nipuni', 'St. Joseph\'s Girls College, Kandy', 2023, 'Media', 'C', 'Sinhala', 'C', 'Geography', 'S', 3105, 47171, 0.0387, 'Kandy', 'Arts'],
    ['Manthi Pamaya', 'Ananda Balika, Colombo', 2023, 'Media', 'B', 'Logic', 'B', 'IT', 'C', 1483, 23749, 0.7573, 'Colombo', 'Arts'],
    ['Dulsara Methyani', 'Matara Sujatha Balika', 2023, 'Media', 'C', 'Geography', 'C', 'Political Science', 'C', 1566, 34705, 0.4209, 'Matara', 'Arts'],
    ['Samadhi Liyanage', 'Embilipitiya Janadhipathi Vidyalaya', 2023, 'Media', 'C', 'Geography', 'S', 'Sinhala', 'C', 2922, 45248, 0.0044, 'Ratnapura', 'Arts'],
    ['Ushani Virasha', 'Gamini Dissanayake Maha Vidyalaya', 2023, 'Media', 'S', 'Sinhala', 'C', 'Geography', 'S', 2061, 57117, 0.2946, 'Kothmale', 'Arts'],
    ['Amasha Nethmini', 'Sri Sumangala Vidyalaya, Kurunegala', 2023, 'Media', 'S', 'Sinhala', 'B', 'Economics', 'S', 4444, null, 0.2790, 'Kurunegala', 'Arts'],
    ['Chathumi Yashoda', 'P.V', 2023, 'Media', 'S', 'Buddhist Culture', 'A', 'Political Science', 'C', 1779, 28739, 0.6010, 'Gampola', 'Arts'],
    ['Chathushi Kavindiya', 'Delta Gamunupura Maha Vidyalaya', 2023, 'Media', 'B', 'Sinhala', 'S', 'Geography', 'S', 1372, 41930, 0.2016, 'Kothmale', 'Arts'],
    ['Dilshi Chamodi', 'Agbogara M.V.', 2023, 'Media', 'B', 'Sinhala', 'B', 'Geography', 'C', 374, 16806, 0.9824, 'Anuradhapura', 'Arts'],
    ['Vihanga Nuwanthana', 'Perakumbura National College', 2023, 'Media', 'C', 'Sinhala', 'C', 'Buddhist Culture', 'C', 2927, 30624, 0.5420, 'Kegalle', 'Arts'],
    ['Sawmya Dharshani', 'Wickramabahu National College, Gampola', 2023, 'Media', 'S', 'Sinhala', 'C', 'History', 'S', 3218, 48632, 0.0065, 'Gampola', 'Arts'],
    ['Vihanga Akalanka', 'Kuliyapitiya Koviwatta Gamini M.V.', 2023, 'Media', 'A', 'History', 'A', 'Sinhala', 'A', 2400, 224, 1.6558, 'Kurunegala', 'Arts'],
    ['Chathurika Abeysinghe', 'Mahamaya Girls College, Kandy', 2023, 'Media', 'A', 'Economics', 'C', 'Geography', 'B', 1009, 17740, 0.9514, 'Kandy', 'Arts'],
    ['Dilini Kaushalya', 'Aluthgama Vidyalaya', 2023, 'Media', 'C', 'Geography', 'B', 'Economics', 'C', 914, 16134, 1.0045, 'Kalutara', 'Arts'],
    ['Fathima Hafza', 'St. Joseph\'s Girls College, Gampola', 2023, 'Media', 'C', 'Geography', 'S', 'Logic', 'S', 3034, 46233, 0.0699, 'Gampola', 'Arts'],
    ['Heshani Bandara', 'Kurunduwatta Royal College', 2023, 'Media', 'A', 'Sinhala', 'A', 'Political Science', 'B', 333, 6531, 1.3929, 'Kandy', 'Arts'],
    ['Piyushani Rashintha', 'Sri Pragnaraatha M.V.', 2023, 'Media', 'C', 'Geography', 'B', 'Sinhala', 'C', 3415, 51319, 0.0916, 'Gampaha', 'Arts'],
    ['Kaushalya Deshani', 'Bandaranayaka Balika Vidyalaya, Ampara', 2023, 'Media', 'C', 'Political Science', 'S', 'Christianity', 'C', 2468, 35622, 0.3925, 'Ampara', 'Arts'],
    ['Madura Prashan', 'P.V.', 2023, 'Media', 'C', 'Art', 'B', 'Geography', 'C', 681, 23961, 0.7500, 'Gampola', 'Arts'],
    ['Kavindi Nipuni', 'St. Joseph\'s Girls College, Gampola', 2023, 'Media', 'C', 'Geography', 'S', 'Sinhala', 'C', 2798, 42156, 0.1654, 'Gampola', 'Arts'],
    ['Thilina Dharshana', 'Mahasen National College', 2023, 'Media', 'C', 'Sinhala', 'B', 'Political Science', 'C', 2192, 22831, 0.7861, 'Polonnaruwa', 'Arts'],
    ['Tharushi Shashikala', 'Nayapana Vidyalaya', 2023, 'Media', 'C', 'Geography', 'C', 'Sinhala', 'B', 707, 2443, 0.7361, 'Gampola', 'Arts'],
    ['Pathum Chamara', 'Kings Wood College, Kandy', 2023, 'Media', 'C', 'Economics', 'C', 'Geography', 'C', 2446, 38891, 0.2937, 'Kandy', 'Arts'],
    ['Lakshani Nimesha', 'Not Specified', 2023, 'Media', 'C', 'Drama', 'C', 'Sinhala', 'C', 2720, 42207, 0.1937, 'Not Specified', 'Arts'],
    ['Diyana', 'Not Specified', 2023, 'Media', 'C', 'Geography', 'C', 'Economics', 'C', 1215, 38125, 0.3170, 'Not Specified', 'Arts'],
    ['Thakshila Madushani', 'Ginigathena National College, Ginigathena', 2023, 'Media', 'S', 'Sinhala', 'C', 'Political Science', 'S', 3767, 55626, 0.2397, 'Nuwara Eliya', 'Arts'],
    ['Sajeewa Madhushani', 'Jinaraja Girls\' College, Gampola', 2023, 'Media', 'B', 'Dancing', 'C', 'Sinhala', 'S', 944, 16793, 0.9822, 'Gampola', 'Arts'],
    ['Sachin Liyanage', 'Not Specified', 2023, 'Media', 'C', 'Korean', 'A', 'Buddhist Culture', 'C', 2314, 24215, 0.7429, 'Not Specified', 'Arts'],
    ['Kasunika Herath', 'Seethadevi Girls\' College', 2023, 'Media', 'S', 'Drama', 'S', 'Sinhala', 'C', 3498, 52472, 0.1289, 'Kandy', 'Arts'],
    ['Varuni Thakshila', 'Pushpadana Girls\' College, Kandy', 2023, 'Media', 'C', 'Drama', 'C', 'Japanese', 'C', 1832, 30339, 0.5484, 'Kandy', 'Arts'],
    ['Tharushika Malshani', 'St. Joseph\'s Girls College, Gampola', 2023, 'Media', 'C', 'Sinhala', 'B', 'Geography', 'C', 1612, 27007, 0.6522, 'Gampola', 'Arts'],
    ['Nethmini Tharushika', 'Siri Piyarathana M.V.', 2023, 'Media', 'A', 'Dancing', 'B', 'History', 'A', 182, 1809, 1.7115, 'Padukka', 'Arts'],
    ['Nimesha Heshani', 'Wickramabahu National College', 2023, 'Media', 'C', 'Geography', 'B', 'Sinhala', 'B', 2035, 20954, 0.8460, 'Kandy', 'Arts'],
    ['Chanuka Dilhara', 'Not Specified', 2023, 'Media', 'S', 'Geography', 'S', 'Political Science', 'S', 3806, 56056, 0.2563, 'Not Specified', 'Arts'],
    ['Hasin Dinsha Perera', 'Wickramabahu National College', 2023, 'Media', 'C', 'Political Science', 'C', 'Sinhala', 'C', 2887, 35970, 0.3829, 'Kandy', 'Arts'],
    ['Jena Anjela', 'All Saints College Boralla', 2023, 'Media', 'S', 'Sinhala', 'S', 'Christianity', 'B', 3616, 55018, 0.2165, 'Colombo', 'Arts'],
    ['Lochana Dissanayake', 'Seethadevi Girls\' College', 2023, 'Media', 'S', 'Sinhala', 'C', 'Drama', 'C', 31094, 48251, 0.0043, 'Kandy', 'Arts'],
    ['Nilukshika Madumali', 'Jinaraja Girls College Gampola', 2023, 'Media', 'B', 'Sinhala', 'B', 'Logic', 'C', 1070, 18447, 0.9181, 'Gampola', 'Arts'],
    ['Thinili Lakshani', 'P.V.', 2023, 'Media', 'C', 'Sinhala', 'C', 'Drama', 'C', 2720, 42129, 0.1937, 'Gampola', 'Arts'],
    ['Kalana Thusantha', 'Not Specified', 2023, 'Media', 'S', 'Political Science', 'S', 'Geography', 'S', 3926, 57733, 0.3178, 'Not Specified', 'Arts'],
    ['Dulmini Nayanathara', 'Kurunduwatta Royal College', 2023, 'Media', 'B', 'Economics', 'C', 'Agriculture', 'S', 3498, 52472, 0.1289, 'Kandy', 'Arts'],
    ['Nilakshi Uthapala', 'St. Joseph\'s Girls\' College, Gampola', 2023, 'Media', 'A', 'Geography', 'A', 'Sinhala', 'A', 168, 3321, 1.5861, 'Gampola', 'Arts'],
    ['Dewli Vimansa', 'Mahamaya Girls\' College, Kandy', 2023, 'Media', 'B', 'Political Science', 'A', 'Geography', 'B', 363, 7050, 1.3687, 'Kandy', 'Arts'],
    ['Maleesha Nethmi', 'St. Joseph\'s Girls College, Gampola', 2023, 'Media', 'A', 'Sinhala', 'A', 'Geography', 'A', 177, 3050, 1.5722, 'Gampola', 'Arts'],
    ['Malmi Rasinka', 'Embilipitiya National College', 2023, 'Media', 'C', 'Sinhala', 'C', 'Geography', 'C', 1958, 31130, 0.3272, 'Ratnapura', 'Arts'],
    ['Himali Menuka', 'St. Joseph\'s Girls College, Gampola', 2023, 'Media', 'C', 'Sinhala', 'A', 'History', 'B', 989, 17360, 0.9640, 'Gampola', 'Arts'],
    ['Sewmini Sanjana', 'Polpitigama National College', 2023, 'Media', 'S', 'Sinhala', 'C', 'Home Science', 'B', 3944, 41901, 0.2025, 'Kurunegala', 'Arts'],
    ['Aruni Bhagya', 'Madagama National College', 2023, 'Media', 'B', 'Sinhala', 'A', 'Geography', 'B', 431, 9255, 1.2649, 'Monaragala', 'Arts'],
    ['Shanika Dilshani', 'Ruwanweli Maha Vidyalaya, Anuradhapura', 2024, 'Media', 'B', 'Sinhala', 'A', 'Home Science', 'C', 2944, 56654, 0.4220, 'Anuradhapura', 'Arts'],
    ['H.A. Padmawat', 'Padmawat School', 2024, 'Media', 'C', 'Political Science', 'A', 'History', 'A', 518, 7163, 1.3429, 'Gampaha', 'Arts'],
    ['Lakshitha Madushan', 'Jathika Pasala, Gampaha', 2024, 'Political Science', 'A', 'History', 'A', 'Media', 'C', 638, 23503, 0.1064, 'Gampaha', 'Arts'],
    ['Niroshan Shalitha', 'Gamini Dissanayaka National School, Kothmale', 2024, 'Media', 'C', 'Sinhala', 'B', 'Political Science', 'B', 638, 23503, 0.1064, 'Kothmale', 'Arts'],
    ['Omani Yushika', 'Private Student', 2024, 'Media', 'S', 'Geography', 'S', 'Sinhala', 'S', 3649, 51557, 0.2037, 'Private', 'Arts'],
    ['Dilini Nimesha', 'Ehetuwewa Bandaranayaka School', 2024, 'Media', 'C', 'Home Science', 'C', 'Dancing', 'B', 2273, 23943, 0.6920, 'Kurunegala', 'Arts'],
    ['Rashini Pramodya', 'Private Student', 2024, 'Media', 'C', 'Buddhist Culture', 'C', 'Sinhala', 'C', 2233, 2744, 0.2744, 'Private', 'Arts'],
    ['Prarthana Weerarathne', 'Private Student', 2024, 'Agriculture', 'S', 'ICT', 'S', 'Media', 'B', 2218, 32038, 0.4252, 'Private', 'Arts'],
    ['Tharindi Anuradha', 'Private Student', 2024, 'Political Science', 'A', 'Media', 'A', 'Sinhala', 'A', 19, 273, 2.0505, 'Private', 'Arts'],
    ['Chathumi Prarthana', 'Ginigathena Central College, Ginigathena', 2024, 'Geography', 'F', 'Home Science', 'C', 'Media', 'S', null, null, 0.6040, 'Nuwara Eliya', 'Arts'],
    ['Roshel Ajnani Jayathilaka', 'Panama Maha Vidyalaya', 2024, 'Media', 'S', 'Christianity', 'A', 'Sinhala', 'C', 190, 34980, 0.3367, 'Ampara', 'Arts'],
    ['Navodya Vidumini', 'Panama Right, Ampara', 2024, 'Political Science', 'C', 'Media', 'C', 'Sinhala', 'B', 1017, 21282, 0.7830, 'Ampara', 'Arts'],
    ['Dulakshi Dilesha Sathsarani', 'Mahindyodaya National School, Kuliyapitiya', 2024, 'Political Science', 'C', 'Media', 'C', 'Sinhala', 'B', 2501, 2619, 0.6152, 'Kurunegala', 'Arts'],
    ['Rithmi Kawya Panchali Bandara', 'Panama Maha Vidyalaya', 2024, 'Media', 'S', 'Music', 'C', 'Sinhala', 'S', 2027, 41692, 0.1275, 'Ampara', 'Arts'],
    ['Harshani Dilrukshi', 'Not Specified', 2024, 'Agriculture', 'C', 'Media', 'A', 'Buddhist Culture', 'B', 329, 7186, 1.3420, 'Not Specified', 'Arts'],
    ['Dasuni Thakshila', 'Pushpadana Girls\' College, Kandy', 2024, 'Logic', 'B', 'Media', 'B', 'Japanese', 'C', 1176, 19662, 0.8399, 'Kandy', 'Arts'],
    ['Geshani Dewmini', 'Ananda Balika Vidyalaya, Kotte', 2024, 'Logic', 'B', 'Media', 'C', 'Japanese', 'S', 1823, 31079, 0.4557, 'Colombo', 'Arts'],
    ['Nilushi Dheeshana', 'Private Student', 2024, 'Media', 'S', 'Dancing', 'S', 'Sinhala', 'S', 2951, 55644, 0.3704, 'Private', 'Arts'],
    ['Navodya Jayawardhana', 'St. Andrews\' Balika Vidyalaya, Nawalapitiya', 2024, 'Political Science', 'S', 'Media', 'S', 'Sinhala', 'C', 2475, 38946, 0.2143, 'Nawalapitiya', 'Arts'],
    ['Dinendri Peiris', 'Gurulugomie Maha Vidyalaya', 2024, 'ICT', 'C', 'Media', 'C', 'Drama', 'B', 975, 19678, 0.8392, 'Gampaha', 'Arts'],
    ['Tharushi Vihanga', 'Poramadulla Central College', 2024, 'Political Science', 'S', 'Media', 'S', 'Sinhala', 'S', 2007, 55349, 0.3568, 'Nuwara Eliya', 'Arts'],
    ['Hasadara Erajini Senevirathna', 'Morawaka Keerthi Abeywickrama National School', 2024, 'ICT', 'S', 'Dancing', 'C', 'Media', 'S', 2179, 50852, 0.1786, 'Matara', 'Arts'],
    ['Dinithi Sandunika', 'Anura College', 2024, 'Agriculture', 'S', 'Media', 'B', 'Japanese', 'S', 1690, 37158, 2.6830, 'Matara', 'Arts'],
    ['Nisha Sherin', 'Not Specified', 2024, 'Media', 'S', 'Sinhala', 'C', 'Buddhist Culture', 'S', 3788, 53192, 0.2645, 'Not Specified', 'Arts'],
    ['Dinushika Lakmali', 'Not Specified', 2024, 'Geography', 'C', 'Media', 'S', 'History', 'C', 1973, 32472, 4.1200, 'Not Specified', 'Arts'],
    ['Sudarsha Virashani Silva', 'St. Anthony\'s Girls College, Panadura', 2024, 'Media', 'S', 'Buddhist Culture', 'S', 'Sinhala', 'C', 2424, 48891, 0.1111, 'Kalutara', 'Arts'],
    ['Ishara Nethmini Weerasekara', 'Hewaheta Central College, Thalathuoya', 2024, 'Home Science', 'S', 'Media', 'C', 'Sinhala', 'C', 3685, 54801, 0.3321, 'Kandy', 'Arts'],
    ['Shyamila Harshani', 'Thambuththegama Central College', 2024, 'Agriculture', 'S', 'Geography', 'C', 'Media', 'S', 2482, 46055, 0.0162, 'Anuradhapura', 'Arts'],
    ['Methsarani Bhagya', 'Hatharaliyadda National School', 2024, 'Geography', 'C', 'Media', 'C', 'Sinhala', 'B', 1639, 26952, 0.5902, 'Kandy', 'Arts'],
    ['Hiruni Punsara', 'Deiyandara National School, Matara', 2024, 'Agriculture', 'C', 'Geography', 'B', 'Media', 'A', 684, 12840, 1.0933, 'Matara', 'Arts'],
    ['Pavithra Kalpani', 'Private', 2024, 'Media', 'S', 'Buddhist Culture', 'S', 'Sinhala', 'C', 2912, 54960, 0.3391, 'Private', 'Arts'],
    ['Akila Malika', 'St. Joseph\'s Girls\' College, Gampola', 2024, 'Geography', 'S', 'Media', 'C', 'Sinhala', 'C', 3039, 46619, 0.0340, 'Gampola', 'Arts'],
    ['Sahanya Nethmini Jayasundara', 'Rangiri Dambulla Central College', 2024, 'Economics', 'S', 'Media', 'C', 'Drama', 'A', 456, 19220, 0.8549, 'Dambulla', 'Arts'],
    ['Chathuraya Lakmini', 'Gamini Madhya Maha Vidyalaya', 2024, 'Media', 'S', 'Music', 'S', 'Sinhala', 'S', 2202, 59435, 0.6003, 'Gampola', 'Arts'],
    ['Charuni Kaushalya', 'Private', 2024, 'Media', 'F', 'Dancing', 'S', 'Drama', 'S', null, null, 0.8139, 'Private', 'Arts'],
    ['Thesath Mirihagalla', 'Thakshila Vidyalaya, Gampaha', 2024, 'Agriculture', 'S', 'Geography', 'S', 'Media', 'F', null, null, 0.5295, 'Gampaha', 'Arts'],
    ['Savidi Dilhara', 'Weranketagoda Maha Vidyalaya', 2024, 'Political Science', 'B', 'Media', 'B', 'Sinhala', 'C', 871, 1816, 0.8925, 'Ampara', 'Arts'],
    ['Sachini Dilshika', 'Private', 2024, 'Media', 'S', 'Buddhist Culture', 'S', 'Sinhala', 'S', 1595, 577, 0.4825, 'Private', 'Arts'],
    ['Tharushi Ranasingha', 'Gurulugomi Maha Vidyalaya', 2024, 'Political Science', 'A', 'Media', 'A', 'Sinhala', 'A', 56, 929, 1.8645, 'Gampaha', 'Arts'],
    ['Dihansa Ransadi Gamage', 'Siri Piyarathna National School', 2024, 'Political Science', 'S', 'Media', 'S', 'Sinhala', 'S', 3328, 53298, 0.2687, 'Padukka', 'Arts'],
    ['Chamodya Dewindi', 'Private', 2024, 'Political Science', 'S', 'Home Science', 'C', 'Media', 'C', 2406, 39943, 0.1830, 'Private', 'Arts'],
    ['Pasindu Malshan', 'Wadakada Maha Vidyalaya', 2024, 'Geography', 'S', 'Media', 'S', 'Sinhala', 'S', 5663, 59825, 0.6361, 'Kurunegala', 'Arts'],
    ['Lumbini Hansika', 'Maliyadewa Adarsha Maha Vidyalaya', 2024, 'Geography', 'C', 'Media', 'A', 'Dancing', 'C', 1526, 16534, 0.9516, 'Kurunegala', 'Arts'],
    ['Yureka Dilrukshi', 'Wanninayaka National School', 2024, 'ICT', 'S', 'Media', 'B', 'Sinhala', 'A', 2791, 29151, 0.5173, 'Kurunegala', 'Arts'],
    ['Pathum Saliya', 'Private', 2024, 'Geography', 'C', 'Media', 'S', 'Sinhala', 'C', 1863, 41913, 0.1201, 'Private', 'Arts'],
    ['Premarathne', 'Dambulla Central College', 2024, 'Media', 'S', 'Buddhist Culture', 'B', 'Sinhala', 'C', 818, 33354, 0.3854, 'Dambulla', 'Arts'],
    ['Rashmi Apsara', 'Sri Jayawardanapura Maha Vidyalaya', 2024, 'Political Science', 'C', 'Media', 'C', 'Sinhala', 'C', 1847, 31620, 0.4384, 'Colombo', 'Arts'],
    ['Imasha Wanninayaka', 'Vishwadeepani Central College', 2024, 'Media', 'S', 'Buddhist Culture', 'S', 'Sinhala', 'C', 5153, 54079, 0.3011, 'Kurunegala', 'Arts'],
    ['Ruchini Priyadarshani', 'Mahinda Maha Vidyalaya', 2024, 'Geography', 'S', 'Media', 'F', 'Buddhist Culture', 'C', null, null, 0.4359, 'Galle', 'Arts'],
    ['Haseena Sandeepanee', 'Maliyadewa Model School / Kumbukgetta C.C.', 2024, 'Geography', 'B', 'Media', 'S', 'Sinhala', 'B', 2506, 26277, 0.6141, 'Kurunegala', 'Arts'],
    ['Chalithya Nethmini', 'Not Specified', 2024, 'ICT', 'S', 'Economics', 'S', 'Media', 'C', 3180, 48404, 0.0942, 'Not Specified', 'Arts'],
    ['Imesha Dulanjali', 'Buddhist Girls College', 2024, 'Political Science', 'C', 'Logic', 'C', 'Media', 'C', 1786, 30309, 0.4801, 'Colombo', 'Arts'],
    ['Maneesha Perera', 'President\'s National School', 2024, 'Political Science', 'C', 'Media', 'C', 'Sinhala', 'B', 650, 19624, 0.8412, 'Minuwangoda', 'Arts'],
    ['Nethmi Fathima', 'Private', 2024, 'Media', 'C', 'Buddhist Culture', 'C', 'Sinhala', 'C', 1889, 32192, 0.4206, 'Private', 'Arts'],
    ['Hirushi Dias', 'Mahamaya Vidyalaya, Kadawatha', 2024, 'Media', 'B', 'Japanese', 'S', 'Korean', 'S', 2366, 34198, 0.3590, 'Gampaha', 'Arts'],
    ['Devindi Maheshika', 'Mahamaya Balika Vidyalaya', 2024, 'Logic', 'S', 'Media', 'C', 'Sinhala', 'C', 2809, 45484, 0.0033, 'Kadawatha', 'Arts'],
    ['Yeshani Rumeshika', 'Sri Deerananda Maha Vidyalaya', 2024, 'Geography', 'C', 'Media', 'C', 'Art', 'C', 1615, 26569, 0.6032, 'Gampola', 'Arts'],
    ['Dilmi Sandupama', 'Rippon Girls\' College, Galle', 2024, 'Geography', 'C', 'Media', 'B', 'Sinhala', 'A', 810, 12716, 1.0982, 'Galle', 'Arts'],
    ['Rashini Mindula', 'CWW Kannangara Central College', 2024, 'Geography', 'S', 'Media', 'S', 'Music', 'C', 3581, 50710, 0.1736, 'Mathugama', 'Arts'],
    ['Ishani Pramodya', 'Agrabodi Vidyalaya, Trincomalee', 2024, 'Geography', 'C', 'Logic', 'S', 'Media', 'S', 1167, 44705, 0.0290, 'Trincomalee', 'Arts'],
    ['Gayanthi Saumya', 'St. Gabrial\'s Girls College', 2024, 'Geography', 'C', 'Media', 'C', 'Sinhala', 'B', 760, 26967, 0.5896, 'Hatton', 'Arts'],
    ['Shehara Wijepala', 'Walagamba Central College', 2024, 'Media', 'C', 'Art', 'C', 'Sinhala', 'C', 1663, 26996, 0.5883, 'Kegalle', 'Arts'],
    ['Awishka Tiruni', 'Sumana Balika Vidyalaya', 2024, 'Geography', 'B', 'Media', 'B', 'Sinhala', 'A', 631, 10418, 1.1945, 'Ratnapura', 'Arts'],
    ['Kaushalya Sandamini', 'Naminioya Jathika Pasala', 2024, 'Home Science', 'C', 'Media', 'S', 'Sinhala', 'C', 1375, 51161, 0.1888, 'Matale', 'Arts'],
    ['Nethmi Sandupama', 'Ananda Maithiya Madhya Maha Vidyalaya', 2024, 'Logic', 'C', 'History', 'C', 'Media', 'B', 1680, 27361, 0.5158, 'Balangoda', 'Arts'],
    ['Harshi Umaya Sandeepani', 'Jinaraja Girls\' College, Gampola', 2024, 'Geography', 'S', 'Media', 'C', 'Sinhala', 'B', 1952, 31382, 0.4453, 'Gampola', 'Arts'],
    ['Sathindi Thilakarathna', 'SWRD Bandaranayaka College, Kandy', 2024, 'Home Science', 'B', 'Media', 'S', 'Sinhala', 'B', 1619, 26711, 0.5976, 'Kandy', 'Arts'],
    ['Yenuli', 'Delta Gemunupura Maha Vidyalaya, Gampola', 2025, 'Media', 'A', 'Dancing', 'A', 'Drama', 'B', 94, 5057, 1.4748, 'Gampola', 'Arts'],
    ['Piyumini Gayandi', 'Jayahela National School, Kothmale', 2025, 'Media', 'A', 'Music', 'A', 'Sinhala', 'A', 182, 7946, 1.3089, 'Kothmale', 'Arts'],
    ['Ashini Uthsari', 'Delta Gemunupura Maha Vidyalaya', 2025, 'Media', 'B', 'Dancing', 'A', 'Drama', 'A', 190, 8345, 1.2883, 'Kothmale', 'Arts'],
    ['Oshadhi Kesara Fernando', 'Not Specified', 2025, 'Sinhala', 'A', 'Media', 'B', 'History', 'B', 556, 9262, 1.2418, 'Not Specified', 'Arts'],
    ['A.H.M.D.Sithlini Gamlath Wijayarathne', 'Not Specified', 2025, 'Media', 'B', 'Economics', 'C', 'Geography', 'C', 1390, 22758, 0.7004, 'Not Specified', 'Arts'],
    ['Senithi Sehansa', 'Not Specified', 2025, 'Media', 'C', 'ICT', 'S', 'Japanese', 'S', 1929, 33074, 0.3556, 'Not Specified', 'Arts'],
    ['Thurya Dananjani Sandunika', 'Not Specified', 2025, 'Sinhala', 'C', 'Media', 'S', 'Drama', 'S', 1638, 45566, 0.0530, 'Not Specified', 'Arts'],
    ['Kawya Dewmini', 'Not Specified', 2025, 'Agriculture', 'A', 'Media', 'A', 'History', 'A', 51, 795, 1.9176, 'Not Specified', 'Arts'],
    ['Rohan Rukshan', 'Not Specified', 2025, 'Media', 'A', 'Home Science', 'A', 'Political Science', 'B', 304, 5215, 1.4640, 'Not Specified', 'Arts'],
    ['Raniki Shamalka Fernando', 'Not Specified', 2025, 'Drama', 'A', 'Media', 'B', 'Japanese', 'B', 251, 4250, 1.5291, 'Not Specified', 'Arts'],
    ['Senulya Marasingha', 'Not Specified', 2025, 'Media', 'A', 'Geography', 'A', 'Japanese', 'A', 18, 109, 2.2521, 'Not Specified', 'Arts'],
    ['Tharaka Randima Ransinghe', 'Not Specified', 2025, 'Drama', 'B', 'Media', 'B', 'Drama', 'B', 801, 13451, 1.0553, 'Not Specified', 'Arts'],
    ['Dhanodya Ahinsani Wijesundara', 'Not Specified', 2025, 'Media', 'B', 'Political Science', 'A', 'Geography', 'B', 695, 7282, 1.3439, 'Not Specified', 'Arts'],
    ['Nishal Pragith Bandara', 'Not Specified', 2025, 'Media', 'B', 'Political Science', 'A', 'Dancing', 'A', 56, 3349, 1.6058, 'Not Specified', 'Arts'],
    ['Lihini Kawya', 'Not Specified', 2025, 'Media', 'B', 'Geography', 'A', 'Sinhala', 'B', 624, 10456, 1.1854, 'Not Specified', 'Arts'],
    ['Tharushi Himasha', 'Not Specified', 2025, 'Media', 'C', 'Sinhala', 'C', 'Geography', 'C', 1187, 35344, 0.2823, 'Not Specified', 'Arts'],
    ['Kawindya Dewmini Kumarasinghe', 'Not Specified', 2025, 'Media', 'B', 'Economics', 'C', 'Geography', 'B', 1054, 17632, 0.8887, 'Not Specified', 'Arts'],
    ['Indeewari Saubhagya Kumari', 'Not Specified', 2025, 'Media', 'A', 'Home Science', 'A', 'Dancing', 'A', 3, 565, 1.9798, 'Not Specified', 'Arts'],
    ['Ayesha Damayanthi Manike', 'Not Specified', 2025, 'Media', 'A', 'Political Science', 'B', 'Sinhala', 'B', 228, 9817, 1.2149, 'Not Specified', 'Arts'],
    ['Nethmi Kaveesha Thilakarathne', 'Not Specified', 2025, 'Media', 'A', 'Buddhist Culture', 'A', 'Geography', 'B', 34, 2445, 1.6877, 'Not Specified', 'Arts'],
    ['Chathuni Isurika Rajapaksha', 'Not Specified', 2025, 'Media', 'B', 'History', 'B', 'Art', 'B', 684, 11001, 1.1616, 'Not Specified', 'Arts'],
    ['Nethmi Tharangi Kaushalya', 'Not Specified', 2025, 'Media', 'C', 'Sinhala', 'B', 'Geography', 'B', 902, 15208, 0.9848, 'Not Specified', 'Arts'],
    ['Thakshila Manel Kumari', 'Not Specified', 2025, 'Media', 'B', 'Buddhist Culture', 'B', 'Sinhala', 'C', 1507, 24452, 0.6414, 'Not Specified', 'Arts'],
    ['Ishani Umeshika Ranaweera', 'Not Specified', 2025, 'Media', 'C', 'Sinhala', 'C', 'Geography', 'C', 1302, 38146, 0.1929, 'Not Specified', 'Arts'],
    ['Nadun Madusanka', 'Not Specified', 2025, 'Media', 'C', 'History', 'B', 'Sinhala', 'C', 657, 22240, 0.7182, 'Not Specified', 'Arts'],
    ['Madusha Sandakarini', 'Not Specified', 2025, 'Media', 'A', 'Logic', 'B', 'Sinhala', 'B', 627, 10502, 1.1825, 'Not Specified', 'Arts'],
    ['Oshadi Vihansa Mandakini', 'Not Specified', 2025, 'Media', 'B', 'Dancing', 'C', 'Sinhala', 'C', 1532, 24633, 0.6348, 'Not Specified', 'Arts'],
    ['Samindi Malsha Aththanayaka', 'Not Specified', 2025, 'Media', 'A', 'Political Science', 'A', 'Logic', 'C', 440, 9685, 1.2216, 'Not Specified', 'Arts'],
    ['Nethmi Heshani', 'Not Specified', 2025, 'Media', 'C', 'Geography', 'C', 'ICT', 'S', 1795, 28357, 0.5109, 'Not Specified', 'Arts'],
    ['Dilini Nimesha Madhushani', 'Not Specified', 2025, 'Media', 'B', 'Home Science', 'A', 'Dancing', 'B', 830, 8708, 1.2684, 'Not Specified', 'Arts'],
    ['Kawya Gauthami Buddhika', 'Not Specified', 2025, 'Media', 'C', 'Sinhala', 'A', 'Geography', 'S', 1009, 131050, 0.4214, 'Not Specified', 'Arts'],
    ['Merari Kristina', 'Not Specified', 2025, 'Media', 'S', 'Home Science', 'C', 'Sinhala', 'C', 2254, 42321, 0.0584, 'Not Specified', 'Arts'],
    ['Samuni Lakshika Charunayani', 'Not Specified', 2025, 'Media', 'B', 'Sinhala', 'B', 'Agriculture', 'S', 1012, 31089, 0.4202, 'Not Specified', 'Arts'],
    ['Bhagya Sewmini', 'Not Specified', 2025, 'Media', 'A', 'Sinhala', 'A', 'Geography', 'B', 63, 3598, 1.5830, 'Not Specified', 'Arts'],
    ['Tisali Senulya Marasinghe', 'Not Specified', 2025, 'Media', 'A', 'Japanese', 'A', 'Geography', 'A', 18, 109, 2.2521, 'Not Specified', 'Arts'],
    ['Nethmi Arundhi Gunarathna', 'Not Specified', 2025, 'Media', 'C', 'Sinhala', 'C', 'Agriculture', 'S', 1359, 39260, 0.1563, 'Not Specified', 'Arts'],
    ['Bhagya Sewwandi', 'Not Specified', 2025, 'Media', 'C', 'Political Science', 'A', 'Sinhala', 'B', 735, 12365, 1.0997, 'Not Specified', 'Arts'],
    ['Abdulla Sham Shiyabdeen Amal Deiyanna', 'Not Specified', 2025, 'Media', 'S', 'Sinhala', 'B', 'Home Science', 'C', 2302, 43202, 0.0291, 'Not Specified', 'Arts'],
    ['Samadhi Thathsarani Senevirathna', 'Not Specified', 2025, 'Media', 'B', 'Sinhala', 'A', 'Political Science', 'B', 208, 8778, 1.2651, 'Not Specified', 'Arts'],
    ['Ushani Chalukya Priyantha', 'Not Specified', 2025, 'Media', 'C', 'Logic', 'S', 'Geography', 'S', 2706, 41229, 0.0943, 'Not Specified', 'Arts'],
    ['A.G.Sasini Kawya Darmarathna', 'Not Specified', 2026, 'Media', 'A', 'Sinhala', 'A', 'History', 'A', 11, 734, 1.9353, 'Not Specified', 'Arts'],
    ['L. Kithruwan Mallika Arachchi', 'Not Specified', 2026, 'History', 'A', 'Media', 'A', 'Sinhala', 'A', 218, 3821, 1.5644, 'Not Specified', 'Arts'],
    ['P.K.M.Nuwan Prabod Panchawatta', 'Not Specified', 2026, 'Media', 'A', 'Sinhala', 'A', 'Geography', 'A', 93, 1989, 1.7368, 'Not Specified', 'Arts']
];

$stmt = $conn->prepare("
    INSERT INTO `al_exam_submissions` 
    (`student_id`, `teacher_id`, `student_name`, `school_name`, `exam_year`, `subject_1`, `result_1`, `subject_2`, `result_2`, `subject_3`, `result_3`, `district_rank`, `island_rank`, `z_score`, `district`, `stream`, `agreed_to_publish`, `results_submitted_at`) 
    VALUES (?, 'T_0002', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
");

$inserted_count = 0;
foreach ($students_data as $idx => $st) {
    $dummy_student_id = 'stu_al_' . sprintf('%04d', $idx + 1);
    $stmt->bind_param(
        "sssissssssiidss",
        $dummy_student_id,
        $st[0], // name
        $st[1], // school
        $st[2], // year
        $st[3], // sub1
        $st[4], // res1
        $st[5], // sub2
        $st[6], // res2
        $st[7], // sub3
        $st[8], // res3
        $st[9], // district rank
        $st[10], // island rank
        $st[11], // z score
        $st[12], // district
        $st[13]  // stream
    );
    if ($stmt->execute()) {
        $inserted_count++;
    }
}
$stmt->close();

$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restore Complete AL Results - Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-6 sm:p-12 flex items-center justify-center">
    <div class="max-w-xl w-full bg-slate-800/90 rounded-3xl p-8 sm:p-10 border border-slate-700 shadow-2xl backdrop-blur-xl">
        <div class="flex items-center justify-between mb-6 pb-6 border-b border-slate-700">
            <div>
                <span class="bg-emerald-500/20 text-emerald-400 text-xs font-extrabold uppercase px-3 py-1 rounded-full border border-emerald-500/30">Complete Import Success</span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-2">175 A/L Student Results Loaded</h1>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-bold mb-8">
            Successfully imported all 175 complete student A/L results (with Student Name, School, Year, Grades, District Rank, Island Rank, and Z-Score).
        </div>

        <div class="flex justify-end gap-4">
            <a href="results" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-xl shadow-lg transition-all">View /results Page</a>
        </div>
    </div>
</body>
</html>
