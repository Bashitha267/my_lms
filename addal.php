<?php
session_start();
require_once __DIR__ . '/config.php';

// Increase execution time limit if necessary
set_time_limit(300);

// Ensure required columns exist in al_exam_submissions table
function ensure_columns(mysqli $conn) {
    $columns = [
        'district_rank' => "INT(11) DEFAULT NULL",
        'island_rank' => "INT(11) DEFAULT NULL",
        'exam_year' => "INT(11) DEFAULT NULL",
        'z_score' => "DECIMAL(6,4) DEFAULT NULL"
    ];

    foreach ($columns as $col => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM `al_exam_submissions` LIKE '{$col}'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE `al_exam_submissions` ADD COLUMN `{$col}` {$definition}");
        }
    }
}

ensure_columns($conn);

// Helper to extract District from Address or School name
function extract_district($text) {
    $text_lower = strtolower($text);

    $mapping = [
        'colombo' => 'Colombo',
        'kandy' => 'Kandy',
        'matale' => 'Matale',
        'gampaha' => 'Gampaha',
        'matara' => 'Matara',
        'galle' => 'Galle',
        'hambanthota' => 'Hambantota',
        'hambantota' => 'Hambantota',
        'ampara' => 'Ampara',
        'kurunegala' => 'Kurunegala',
        'kurunagala' => 'Kurunegala',
        'kuliyapitiya' => 'Kurunegala',
        'anuradhapura' => 'Anuradhapura',
        'trincomalee' => 'Trincomalee',
        'trincomale' => 'Trincomalee',
        'gampola' => 'Kandy',
        'kothmale' => 'Nuwara Eliya',
        'nuwara eliya' => 'Nuwara Eliya',
        'ginigathena' => 'Nuwara Eliya',
        'ginigathhena' => 'Nuwara Eliya',
        'panadura' => 'Kalutara',
        'kalutara' => 'Kalutara',
        'boralla' => 'Colombo',
        'kotte' => 'Colombo',
        'baththaramulla' => 'Colombo',
        'nawalapitiya' => 'Kandy',
        'kegalle' => 'Kegalle',
        'badulla' => 'Badulla',
        'ratnapura' => 'Ratnapura',
        'jaffna' => 'Jaffna'
    ];

    foreach ($mapping as $key => $dist) {
        if (strpos($text_lower, $key) !== false) {
            return $dist;
        }
    }

    return 'Kandy'; // Default district if not found
}

// Clean Subject Name to avoid duplicates & standardize names
function clean_subject_name($name) {
    $name = trim(strtoupper($name));
    $name = str_replace(['POLITICA L', 'POLITICA L SC.', 'POLITICAL SC.'], 'POLITICAL SCIENCE', $name);
    $name = str_replace(['HOME EC.', 'HOME ECO'], 'HOME ECONOMICS', $name);
    $name = str_replace('O.MUSIC', 'MUSIC', $name);
    $name = str_replace('BC', 'BUDDHIST CULTURE', $name);
    $name = str_replace('CHRISTIAN', 'CHRISTIANITY', $name);
    $name = str_replace('AGRI SCIENCE', 'AGRICULTURE', $name);
    $name = str_replace('AGRI', 'AGRICULTURE', $name);
    $name = str_replace('ECON', 'ECONOMICS', $name);
    $name = str_replace('JAPAN', 'JAPANESE', $name);
    $name = str_replace('HIISTORY', 'HISTORY', $name);
    return $name;
}

// Extract clean numeric Exam Year from academic year string
function extract_exam_year($year_str) {
    preg_match('/\d{4}/', $year_str, $matches);
    return !empty($matches[0]) ? (int)$matches[0] : 2024;
}

// Extracted Student Results Summary Data
$students = [
    [
        "name" => "Imali Jayawardhana",
        "school" => "Sri Parackrama National School, Matale",
        "academic_year" => "2021 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "BC", "result" => "B"],
            ["subject" => "SINHALA", "result" => "A"]
        ],
        "district_rank" => 367,
        "island_rank" => 13523,
        "z_score" => 1.1459
    ],
    [
        "name" => "Hashinika Wijesingha",
        "school" => "Girls' Highschool, Kandy",
        "academic_year" => "2022 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "ICT", "result" => "S"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 2818,
        "island_rank" => 43174,
        "z_score" => 0.1473
    ],
    [
        "name" => "Avishka Pasindu Vijayanga",
        "school" => "Gamini Dissanayaka National School, Kothmale",
        "academic_year" => "2022 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => null,
        "island_rank" => null,
        "z_score" => null
    ],
    [
        "name" => "Kavindya Madugale",
        "school" => "Seethadevi Girls' College, Kandy",
        "academic_year" => "2022 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "DANCING", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => null,
        "island_rank" => null,
        "z_score" => 0.1202
    ],
    [
        "name" => "Chanchala Hathurusinghe",
        "school" => "Delta Gamunupura Kothmale",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "HISTORY", "result" => "C"]
        ],
        "district_rank" => 1369,
        "island_rank" => 42480,
        "z_score" => 1.8470
    ],
    [
        "name" => "Navodya Lakshani",
        "school" => "Godapitiya National School, Matara",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "POLITICAL SCIENCE", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"]
        ],
        "district_rank" => 312,
        "island_rank" => 5959,
        "z_score" => 1.4236
    ],
    [
        "name" => "Virgini Shara",
        "school" => "All Saints College Boralla",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "B"],
            ["subject" => "CHRISTIANITY", "result" => "A"]
        ],
        "district_rank" => 708,
        "island_rank" => 10267,
        "z_score" => 1.2226
    ],
    [
        "name" => "Upeksha Kavindi",
        "school" => "Wickckramabahu National College, Kandy",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 1538,
        "island_rank" => 25949,
        "z_score" => 0.6872
    ],
    [
        "name" => "Thushantha Karunatilaka",
        "school" => "Not Specified",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "POLITICAL SCIENCE", "result" => "S"]
        ],
        "district_rank" => 3926,
        "island_rank" => 57733,
        "z_score" => 0.3178
    ],
    [
        "name" => "Thakshila Sewwandi Kumari",
        "school" => "Not Specified",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "DANCING", "result" => "C"]
        ],
        "district_rank" => 1482,
        "island_rank" => 25041,
        "z_score" => 0.7158
    ],
    [
        "name" => "Diyana Dhaneshwari Ariyarathna",
        "school" => "Not Specified",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "HOME SCIENCE", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 2799,
        "island_rank" => 43166,
        "z_score" => 0.1654
    ],
    [
        "name" => "Limasha Kavindi",
        "school" => "Rajapaksha National College, Hambanthota",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "POLITICAL SCIENCE", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 1582,
        "island_rank" => 42399,
        "z_score" => 0.1872
    ],
    [
        "name" => "Pujani Nuwandika",
        "school" => "Nayapana M.V",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "ECONOMICS", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "C"]
        ],
        "district_rank" => 1215,
        "island_rank" => 38125,
        "z_score" => 0.3170
    ],
    [
        "name" => "Sonadi Wijewickckrama",
        "school" => "Siridhamma College, Galle",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "HISTORY", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "S"]
        ],
        "district_rank" => 2536,
        "island_rank" => 45434,
        "z_score" => 0.8510
    ],
    [
        "name" => "Fathima Rizna",
        "school" => "Badrdinmahnud Balika Viddyalaya, Kandy",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "POLITICAL SCIENCE", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 4089,
        "island_rank" => 39948,
        "z_score" => 0.4566
    ],
    [
        "name" => "Senuri Kaushalya",
        "school" => "Vidyaawardhana Maha Viddyala, Baththaramulla",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "POLITICAL SCIENCE", "result" => "S"]
        ],
        "district_rank" => 3040,
        "island_rank" => 47560,
        "z_score" => 0.2620
    ],
    [
        "name" => "Malsha Prabodha",
        "school" => "Wickckramabahu National College, Kandy",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 1425,
        "island_rank" => 24219,
        "z_score" => 0.7422
    ],
    [
        "name" => "Samindi Nipuni",
        "school" => "St. Joseph's Girls College, Kandy",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "S"]
        ],
        "district_rank" => 3105,
        "island_rank" => 47171,
        "z_score" => 0.0387
    ],
    [
        "name" => "Manthi Pamaya",
        "school" => "Ananda Balika, Colombo",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "LOGIC", "result" => "B"],
            ["subject" => "ICT", "result" => "C"]
        ],
        "district_rank" => 1483,
        "island_rank" => 23749,
        "z_score" => 0.7573
    ],
    [
        "name" => "Dulsara Methyani",
        "school" => "Matara Sujatha Balika",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "POLITICAL SCIENCE", "result" => "C"]
        ],
        "district_rank" => 1566,
        "island_rank" => 34705,
        "z_score" => 0.4209
    ],
    [
        "name" => "Samadhi Liyanage",
        "school" => "Ambilipitiya Janadhipathi Vidyalaya",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 2922,
        "island_rank" => 45248,
        "z_score" => 0.0044
    ],
    [
        "name" => "Ushani Virasha",
        "school" => "Gamini Dissanayake Maha Vidyalaya",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "S"]
        ],
        "district_rank" => 2061,
        "island_rank" => 57117,
        "z_score" => 0.2946
    ],
    [
        "name" => "Amasha Nethmini",
        "school" => "Sri Sumangala Viddyalaya, Kurunagala",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "B"],
            ["subject" => "ECONOMICS", "result" => "S"]
        ],
        "district_rank" => 4444,
        "island_rank" => null,
        "z_score" => 0.2790
    ],
    [
        "name" => "Chathumi Yashoda",
        "school" => "P.V",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "BUDDHIST CULTURE", "result" => "A"],
            ["subject" => "POLITICAL SCIENCE", "result" => "C"]
        ],
        "district_rank" => 1779,
        "island_rank" => 28739,
        "z_score" => 0.6010
    ],
    [
        "name" => "Chathushi Kavindiya",
        "school" => "Delta Gamunupura Maha Vidyalaya",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "S"],
            ["subject" => "GEOGRAPHY", "result" => "S"]
        ],
        "district_rank" => 1372,
        "island_rank" => 41930,
        "z_score" => 0.2016
    ],
    [
        "name" => "Dilshi Chamodi",
        "school" => "Agbogara M.V.",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "B"],
            ["subject" => "GEOGRAPHY", "result" => "C"]
        ],
        "district_rank" => 374,
        "island_rank" => 16806,
        "z_score" => 0.9824
    ],
    [
        "name" => "Vihanga Nuwanthana",
        "school" => "Perakumbura National College",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "BUDDHIST CULTURE", "result" => "C"]
        ],
        "district_rank" => 2927,
        "island_rank" => 30624,
        "z_score" => 0.5420
    ],
    [
        "name" => "Sawmya Dharshani",
        "school" => "Wickckramabahu National College, Gampola",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "HISTORY", "result" => "S"]
        ],
        "district_rank" => 3218,
        "island_rank" => 48632,
        "z_score" => 0.0065
    ],
    [
        "name" => "Vihanga Akalanka",
        "school" => "Kuliyapitiya Koviwatta Gamini M.V.",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "HISTORY", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"]
        ],
        "district_rank" => 2400,
        "island_rank" => 224,
        "z_score" => 1.6558
    ],
    [
        "name" => "Chathurika Abeysinghe",
        "school" => "Mahamaya Girls College, Kandy",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "ECONOMICS", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "B"]
        ],
        "district_rank" => 1009,
        "island_rank" => 17740,
        "z_score" => 0.9514
    ],
    [
        "name" => "Dilini Kaushalya",
        "school" => "Aluthgama Vidyalaya",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "B"],
            ["subject" => "ECONOMICS", "result" => "C"]
        ],
        "district_rank" => 914,
        "island_rank" => 16134,
        "z_score" => 1.0045
    ],
    [
        "name" => "Fathima Hafza",
        "school" => "St. Joesph's Girls College, Gampola",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "LOGIC", "result" => "S"]
        ],
        "district_rank" => 3034,
        "island_rank" => 46233,
        "z_score" => 0.0699
    ],
    [
        "name" => "Heshani Bandara",
        "school" => "Kurunduwatta Royal College",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"],
            ["subject" => "POLITICAL SCIENCE", "result" => "B"]
        ],
        "district_rank" => 333,
        "island_rank" => 6531,
        "z_score" => 1.3929
    ],
    [
        "name" => "Piyushani Rashintha",
        "school" => "Sri Pragnaraatha M.V.",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "B"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 3415,
        "island_rank" => 51319,
        "z_score" => 0.0916
    ],
    [
        "name" => "Kaushalya Deshani",
        "school" => "Bandaranayaka Balika Viddyalaya, Ampara",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "POLITICAL SCIENCE", "result" => "S"],
            ["subject" => "CHRISTIANITY", "result" => "C"]
        ],
        "district_rank" => 2468,
        "island_rank" => 35622,
        "z_score" => 0.3925
    ],
    [
        "name" => "Madura Prashan",
        "school" => "P.V.",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "ART", "result" => "B"],
            ["subject" => "GEOGRAPHY", "result" => "C"]
        ],
        "district_rank" => 681,
        "island_rank" => 23961,
        "z_score" => 0.7500
    ],
    [
        "name" => "Kavindi Nipuni",
        "school" => "St. Joesph's Girls College, Gampola",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 2798,
        "island_rank" => 42156,
        "z_score" => 0.1654
    ],
    [
        "name" => "Thilina Dharshana",
        "school" => "Mahasen National College",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"],
            ["subject" => "POLITICAL SCIENCE", "result" => "C"]
        ],
        "district_rank" => 2192,
        "island_rank" => 22831,
        "z_score" => 0.7861
    ],
    [
        "name" => "Tharushi Shashikala",
        "school" => "Nayapana Vidyalaya",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 707,
        "island_rank" => 2443,
        "z_score" => 0.7361
    ],
    [
        "name" => "Pathum Chamara",
        "school" => "Kings Wood College, Kandy",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "ECONOMICS", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "C"]
        ],
        "district_rank" => 2446,
        "island_rank" => 38891,
        "z_score" => 0.2937
    ],
    [
        "name" => "Lakshani Nimesha",
        "school" => "Not Specified",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "DRAMA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 2720,
        "island_rank" => 42207,
        "z_score" => 0.1938
    ],
    [
        "name" => "Diyana",
        "school" => "Not Specified",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "ECONOMICS", "result" => "C"]
        ],
        "district_rank" => 1215,
        "island_rank" => 38125,
        "z_score" => 0.3170
    ],
    [
        "name" => "Thakshila Madushani",
        "school" => "Ginigathena National College, Ginigathena",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "POLITICAL SCIENCE", "result" => "S"]
        ],
        "district_rank" => 3767,
        "island_rank" => 55626,
        "z_score" => 0.2397
    ],
    [
        "name" => "Sajeewa Madhushani",
        "school" => "Jinaraja Girls' College, Gampola",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "DANCING", "result" => "C"],
            ["subject" => "SINHALA", "result" => "S"]
        ],
        "district_rank" => 944,
        "island_rank" => 16793,
        "z_score" => 0.9822
    ],
    [
        "name" => "Sachin Liyanage",
        "school" => "Not Specified",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "KOREAN", "result" => "A"],
            ["subject" => "BUDDHIST CULTURE", "result" => "C"]
        ],
        "district_rank" => 2314,
        "island_rank" => 24215,
        "z_score" => 0.7429
    ],
    [
        "name" => "Kasunika Herath",
        "school" => "Seethadevi Girls' College, Kandy",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "DRAMA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 3498,
        "island_rank" => 52472,
        "z_score" => 0.1289
    ],
    [
        "name" => "Varuni Thakshila",
        "school" => "Pushpadana Girls' College, Kandy",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "DRAMA", "result" => "C"],
            ["subject" => "JAPANESE", "result" => "C"]
        ],
        "district_rank" => 1832,
        "island_rank" => 30339,
        "z_score" => 0.5484
    ],
    [
        "name" => "Tharushika Malshani",
        "school" => "St. Joesph's Girls College, Gampola",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"],
            ["subject" => "GEOGRAPHY", "result" => "C"]
        ],
        "district_rank" => 1612,
        "island_rank" => 27007,
        "z_score" => 0.6522
    ],
    [
        "name" => "Nethmini Tharushika",
        "school" => "Siri Piyarathana M.V.",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "DANCING", "result" => "B"],
            ["subject" => "HISTORY", "result" => "A"]
        ],
        "district_rank" => 182,
        "island_rank" => 1809,
        "z_score" => 1.7115
    ],
    [
        "name" => "Nimesha Heshani",
        "school" => "Wickckramabahu National College",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "B"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 2035,
        "island_rank" => 20954,
        "z_score" => 0.8460
    ],
    [
        "name" => "Chanuka Dilhara",
        "school" => "Not Specified",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "POLITICAL SCIENCE", "result" => "S"]
        ],
        "district_rank" => 3806,
        "island_rank" => 56056,
        "z_score" => 0.2563
    ],
    [
        "name" => "Hasin Dinsha Perera",
        "school" => "Wickckramabahu National College",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "POLITICAL SCIENCE", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 2887,
        "island_rank" => 35970,
        "z_score" => 0.3829
    ],
    [
        "name" => "Jena Anjela",
        "school" => "All Saints College Boralla",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "S"],
            ["subject" => "CHRISTIANITY", "result" => "B"]
        ],
        "district_rank" => 3616,
        "island_rank" => 55018,
        "z_score" => 0.2165
    ],
    [
        "name" => "Lochana Dissanayake",
        "school" => "Seethadevi Girls' College",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "DRAMA", "result" => "C"]
        ],
        "district_rank" => 31094,
        "island_rank" => 48251,
        "z_score" => 0.0043
    ],
    [
        "name" => "Nilukshika Madumali",
        "school" => "Jinaraja Girls College Gampola",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "B"],
            ["subject" => "LOGIC", "result" => "C"]
        ],
        "district_rank" => 1070,
        "island_rank" => 18447,
        "z_score" => 0.9181
    ],
    [
        "name" => "Thinili Lakshani",
        "school" => "P.V.",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "DRAMA", "result" => "C"]
        ],
        "district_rank" => 2720,
        "island_rank" => 42129,
        "z_score" => 0.1937
    ],
    [
        "name" => "Kalana Thusantha",
        "school" => "Not Specified",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "POLITICAL SCIENCE", "result" => "S"],
            ["subject" => "GEOGRAPHY", "result" => "S"]
        ],
        "district_rank" => 3926,
        "island_rank" => 57733,
        "z_score" => 0.3178
    ],
    [
        "name" => "Dulmini Nayanathara",
        "school" => "Kurunduwatta Royal College",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "ECONOMICS", "result" => "C"],
            ["subject" => "AGRICULTURE", "result" => "S"]
        ],
        "district_rank" => 3498,
        "island_rank" => 52472,
        "z_score" => 0.1289
    ],
    [
        "name" => "Nilakshi Uthapala",
        "school" => "St. Joesph's Girls' College, Gampola",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"]
        ],
        "district_rank" => 168,
        "island_rank" => 3321,
        "z_score" => 1.5861
    ],
    [
        "name" => "Dewli Vimansa",
        "school" => "Mahamaya Girls' College, Kandy",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "POLITICAL SCIENCE", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "B"]
        ],
        "district_rank" => 363,
        "island_rank" => 7050,
        "z_score" => 1.3687
    ],
    [
        "name" => "Maleesha Nethmi",
        "school" => "St. Joesph's Girls College, Gampola",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "A"]
        ],
        "district_rank" => 177,
        "island_rank" => 3050,
        "z_score" => 1.5722
    ],
    [
        "name" => "Malmi Rasinka",
        "school" => "Ambilipitiya National College",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "C"]
        ],
        "district_rank" => 1958,
        "island_rank" => 31130,
        "z_score" => 0.3272
    ],
    [
        "name" => "Himali Menuka",
        "school" => "St. Joesph's Girls College, Gampola",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "A"],
            ["subject" => "HISTORY", "result" => "B"]
        ],
        "district_rank" => 989,
        "island_rank" => 17360,
        "z_score" => 0.9640
    ],
    [
        "name" => "Sewmini Sanjana",
        "school" => "Polpitigama National College",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "HOME SCIENCE", "result" => "B"]
        ],
        "district_rank" => 3944,
        "island_rank" => 41901,
        "z_score" => 0.2025
    ],
    [
        "name" => "Aruni Bhagya",
        "school" => "Madagama National College",
        "academic_year" => "2023 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "B"]
        ],
        "district_rank" => 431,
        "island_rank" => 9255,
        "z_score" => 1.2649
    ],
    [
        "name" => "Shanika Dilshani",
        "school" => "Ruwanweli Maha Vidyalaya, Anuradhapura",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "A"],
            ["subject" => "HOME SCIENCE", "result" => "C"]
        ],
        "district_rank" => 2944,
        "island_rank" => 56654,
        "z_score" => 0.4220
    ],
    [
        "name" => "H.A. Padmawathi",
        "school" => "Jathika Pasala, Gampaha",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "POLITICAL SCIENCE", "result" => "A"],
            ["subject" => "HISTORY", "result" => "A"]
        ],
        "district_rank" => 518,
        "island_rank" => 7163,
        "z_score" => 1.3429
    ],
    [
        "name" => "Niroshan Shalitha",
        "school" => "Gamini Dissanayaka National School, Kothmale",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"],
            ["subject" => "POLITICAL SCIENCE", "result" => "B"]
        ],
        "district_rank" => 638,
        "island_rank" => 23503,
        "z_score" => 0.1064
    ],
    [
        "name" => "Omani Yushika",
        "school" => "Private Student",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "SINHALA", "result" => "S"]
        ],
        "district_rank" => 3649,
        "island_rank" => 51557,
        "z_score" => 0.2037
    ],
    [
        "name" => "Dilini Nimesha",
        "school" => "Ehetuwewa Bandaranayaka School",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "HOME SCIENCE", "result" => "C"],
            ["subject" => "DANCING", "result" => "B"]
        ],
        "district_rank" => 2273,
        "island_rank" => 23943,
        "z_score" => 0.6920
    ],
    [
        "name" => "Rashini Pramodya",
        "school" => "Private Student",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "BUDDHIST CULTURE", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 2233,
        "island_rank" => 2744,
        "z_score" => 0.2744
    ],
    [
        "name" => "Prarthana Weerarathne",
        "school" => "Private Student",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "AGRICULTURE", "result" => "S"],
            ["subject" => "ICT", "result" => "S"],
            ["subject" => "MEDIA", "result" => "B"]
        ],
        "district_rank" => 2218,
        "island_rank" => 32038,
        "z_score" => 0.4252
    ],
    [
        "name" => "Tharindi Anuradha",
        "school" => "Private Student",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "A"],
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"]
        ],
        "district_rank" => 19,
        "island_rank" => 273,
        "z_score" => 2.0505
    ],
    [
        "name" => "Chathumi Prarthana",
        "school" => "Ginigathena Central College, Ginigathena",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "F"],
            ["subject" => "HOME ECONOMICS", "result" => "C"],
            ["subject" => "MEDIA", "result" => "S"]
        ],
        "district_rank" => null,
        "island_rank" => null,
        "z_score" => 0.6040
    ],
    [
        "name" => "Roshel Ajnani Jayathilaka",
        "school" => "Panama Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "CHRISTIANITY", "result" => "A"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 190,
        "island_rank" => 34980,
        "z_score" => 0.3367
    ],
    [
        "name" => "Navodya Vidumini",
        "school" => "Panama Right, Ampara",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "C"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 1017,
        "island_rank" => 21282,
        "z_score" => 0.7830
    ],
    [
        "name" => "Dulakshi Dilesha Sathsarani",
        "school" => "Mahindyodaya National School, Kuliyapitiya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "C"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 2501,
        "island_rank" => 2619,
        "z_score" => 0.6152
    ],
    [
        "name" => "Rithmi Kawya Panchali Bandara",
        "school" => "Panama Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "MUSIC", "result" => "C"],
            ["subject" => "SINHALA", "result" => "S"]
        ],
        "district_rank" => 2027,
        "island_rank" => 41692,
        "z_score" => 0.1275
    ],
    [
        "name" => "Harshani Dilrukshi",
        "school" => "Not Specified",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "AGRICULTURE", "result" => "C"],
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "BUDDHIST CULTURE", "result" => "B"]
        ],
        "district_rank" => 329,
        "island_rank" => 7186,
        "z_score" => 1.3420
    ],
    [
        "name" => "Dasuni Thakshila",
        "school" => "Pushpadana Girls' College, Kandy",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "LOGIC", "result" => "B"],
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "JAPANESE", "result" => "C"]
        ],
        "district_rank" => 1176,
        "island_rank" => 19662,
        "z_score" => 0.8399
    ],
    [
        "name" => "Geshani Dewmini",
        "school" => "Ananda Balika Vidyalaya, Kotte",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "LOGIC", "result" => "B"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "JAPANESE", "result" => "S"]
        ],
        "district_rank" => 1823,
        "island_rank" => 31079,
        "z_score" => 0.4557
    ],
    [
        "name" => "Nilushi Dheeshana",
        "school" => "Private Student",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "DANCING", "result" => "S"],
            ["subject" => "SINHALA", "result" => "S"]
        ],
        "district_rank" => 2951,
        "island_rank" => 55644,
        "z_score" => 0.3704
    ],
    [
        "name" => "Navodya Jayawardhana",
        "school" => "St. Andrews' Balika Vidyalaya, Nawalapitiya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "S"],
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 2475,
        "island_rank" => 38946,
        "z_score" => 0.2143
    ],
    [
        "name" => "Dinendri Peiris",
        "school" => "Gurulugomie Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "ICT", "result" => "C"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "DRAMA", "result" => "B"]
        ],
        "district_rank" => 975,
        "island_rank" => 19678,
        "z_score" => 0.8392
    ],
    [
        "name" => "Tharushi Vihanga",
        "school" => "Poramadulla Central College",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "S"],
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "S"]
        ],
        "district_rank" => 2007,
        "island_rank" => 55349,
        "z_score" => 0.3568
    ],
    [
        "name" => "Hasadara Erajini Senevirathna",
        "school" => "Morawaka Keerthi Abeywickrama National School",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "ICT", "result" => "S"],
            ["subject" => "DANCING", "result" => "C"],
            ["subject" => "MEDIA", "result" => "S"]
        ],
        "district_rank" => 2179,
        "island_rank" => 50852,
        "z_score" => 0.1786
    ],
    [
        "name" => "Dinithi Sandunika",
        "school" => "Anura College",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "AGRICULTURE", "result" => "S"],
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "JAPANESE", "result" => "S"]
        ],
        "district_rank" => 1690,
        "island_rank" => 37158,
        "z_score" => 2.6830
    ],
    [
        "name" => "Nisha Sherin",
        "school" => "Not Specified",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "BUDDHIST CULTURE", "result" => "S"]
        ],
        "district_rank" => 3788,
        "island_rank" => 53192,
        "z_score" => 0.2645
    ],
    [
        "name" => "Dinushika Lakmali",
        "school" => "Not Specified",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "HISTORY", "result" => "C"]
        ],
        "district_rank" => 1973,
        "island_rank" => 32472,
        "z_score" => 4.1200
    ],
    [
        "name" => "Sudarsha Virashani Silva",
        "school" => "St. Anthony's Girls College, Panadura",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "BUDDHIST CULTURE", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 2424,
        "island_rank" => 48891,
        "z_score" => 0.1111
    ],
    [
        "name" => "Ishara Nethmini Weerasekara",
        "school" => "Hewaheta Central College, Thalathuoya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "HOME ECONOMICS", "result" => "S"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 3685,
        "island_rank" => 54801,
        "z_score" => 0.3321
    ],
    [
        "name" => "Shyamila Harshani",
        "school" => "Thambuttegama Central College",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "AGRICULTURE", "result" => "S"],
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "MEDIA", "result" => "S"]
        ],
        "district_rank" => 2482,
        "island_rank" => 46055,
        "z_score" => 0.0162
    ],
    [
        "name" => "Methsarani Bhagya",
        "school" => "Hatharaliyadda National School",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 1639,
        "island_rank" => 26952,
        "z_score" => 0.5902
    ],
    [
        "name" => "Hiruni Punsara",
        "school" => "Deiyandara National School, Matara",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "AGRICULTURE", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "B"],
            ["subject" => "MEDIA", "result" => "A"]
        ],
        "district_rank" => 684,
        "island_rank" => 12840,
        "z_score" => 1.0933
    ],
    [
        "name" => "Pavithra Kalpani",
        "school" => "Private Student",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "BUDDHIST CULTURE", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 2912,
        "island_rank" => 54960,
        "z_score" => 0.3391
    ],
    [
        "name" => "Akila Malika",
        "school" => "St. Joseph's Girls' College, Gampola",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 3039,
        "island_rank" => 46619,
        "z_score" => 0.0340
    ],
    [
        "name" => "Sahanya Nethmini Jayasundara",
        "school" => "Rangiri Dambulla Central College",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "ECONOMICS", "result" => "S"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "DRAMA", "result" => "A"]
        ],
        "district_rank" => 456,
        "island_rank" => 19220,
        "z_score" => 0.8549
    ],
    [
        "name" => "Chathuraya Lakmini",
        "school" => "Gamini Madya Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "MUSIC", "result" => "S"],
            ["subject" => "SINHALA", "result" => "S"]
        ],
        "district_rank" => 2202,
        "island_rank" => 59435,
        "z_score" => 0.6003
    ],
    [
        "name" => "Charuni Kaushalya",
        "school" => "Private Student",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "F"],
            ["subject" => "DANCING", "result" => "S"],
            ["subject" => "DRAMA", "result" => "S"]
        ],
        "district_rank" => null,
        "island_rank" => null,
        "z_score" => 0.8139
    ],
    [
        "name" => "Thesath Mirihagalla",
        "school" => "Thakshila Vidyalaya, Gampaha",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "AGRICULTURE", "result" => "S"],
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "MEDIA", "result" => "F"]
        ],
        "district_rank" => null,
        "island_rank" => null,
        "z_score" => 0.5295
    ],
    [
        "name" => "Savidi Dilhara",
        "school" => "Weranketagoda Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "B"],
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 871,
        "island_rank" => 1816,
        "z_score" => 0.8925
    ],
    [
        "name" => "Sachini Dilshika",
        "school" => "Private Student",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "BUDDHIST CULTURE", "result" => "S"],
            ["subject" => "SINHALA", "result" => "S"]
        ],
        "district_rank" => 1595,
        "island_rank" => 577,
        "z_score" => 0.4825
    ],
    [
        "name" => "Tharushi Ranasingha",
        "school" => "Gurulugomi Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "A"],
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"]
        ],
        "district_rank" => 56,
        "island_rank" => 929,
        "z_score" => 1.8645
    ],
    [
        "name" => "Dihansa Ransadi Gamage",
        "school" => "Siri Piyarathna National School",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "S"],
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "S"]
        ],
        "district_rank" => 3328,
        "island_rank" => 53298,
        "z_score" => 0.2687
    ],
    [
        "name" => "Chamodya Dewindi",
        "school" => "Private Student",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "S"],
            ["subject" => "HOME ECONOMICS", "result" => "C"],
            ["subject" => "MEDIA", "result" => "C"]
        ],
        "district_rank" => 2406,
        "island_rank" => 39943,
        "z_score" => 0.1830
    ],
    [
        "name" => "Pasindu Malshan",
        "school" => "Wadakada Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "S"]
        ],
        "district_rank" => 5663,
        "island_rank" => 59825,
        "z_score" => 0.6361
    ],
    [
        "name" => "Lumbini Hansika",
        "school" => "Maliyadeva Adarsha Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "DANCING", "result" => "C"]
        ],
        "district_rank" => 1526,
        "island_rank" => 16534,
        "z_score" => 0.9516
    ],
    [
        "name" => "Yureka Dilrukshi",
        "school" => "Wanninayaka National School",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "ICT", "result" => "S"],
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "A"]
        ],
        "district_rank" => 2791,
        "island_rank" => 29151,
        "z_score" => 0.5173
    ],
    [
        "name" => "Pathum Saliya",
        "school" => "Private Student",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 1863,
        "island_rank" => 41913,
        "z_score" => 0.1201
    ],
    [
        "name" => "Premarathne",
        "school" => "Dambulla Central College",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "BUDDHIST CULTURE", "result" => "B"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 818,
        "island_rank" => 33354,
        "z_score" => 0.3854
    ],
    [
        "name" => "Rashmi Apsara",
        "school" => "Sri Jayawardhanapura Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "C"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 1847,
        "island_rank" => 31620,
        "z_score" => 0.4384
    ],
    [
        "name" => "Imasha Wanninayaka",
        "school" => "Vishwadeepani Central College",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "BUDDHIST CULTURE", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 5153,
        "island_rank" => 54079,
        "z_score" => 0.3011
    ],
    [
        "name" => "Ruchini Priyadarshani",
        "school" => "Mahinda Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "MEDIA", "result" => "F"],
            ["subject" => "BUDDHIST CULTURE", "result" => "C"]
        ],
        "district_rank" => null,
        "island_rank" => null,
        "z_score" => 0.4359
    ],
    [
        "name" => "Haseena Sandeepanee",
        "school" => "Maliyadewa Model School / Kumbukgetta C.C.",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "B"],
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 2506,
        "island_rank" => 26277,
        "z_score" => 0.6141
    ],
    [
        "name" => "Chalithya Nethmini",
        "school" => "Not Specified",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "ICT", "result" => "S"],
            ["subject" => "ECONOMICS", "result" => "S"],
            ["subject" => "MEDIA", "result" => "C"]
        ],
        "district_rank" => 3180,
        "island_rank" => 48404,
        "z_score" => 0.0942
    ],
    [
        "name" => "Imesha Dulanjali",
        "school" => "Buddhist Girls College",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "C"],
            ["subject" => "LOGIC", "result" => "C"],
            ["subject" => "MEDIA", "result" => "C"]
        ],
        "district_rank" => 1786,
        "island_rank" => 30309,
        "z_score" => 0.4801
    ],
    [
        "name" => "Maneesha Perera",
        "school" => "President's National School",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "POLITICAL SCIENCE", "result" => "C"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 650,
        "island_rank" => 19624,
        "z_score" => 0.8412
    ],
    [
        "name" => "Nethmi Fathima",
        "school" => "Private Student",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "BUDDHIST CULTURE", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 1889,
        "island_rank" => 32192,
        "z_score" => 0.4206
    ],
    [
        "name" => "Hirushi Dias",
        "school" => "Mahamaya Vidyalaya, Kadawatha",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "JAPANESE", "result" => "S"],
            ["subject" => "KOREAN", "result" => "S"]
        ],
        "district_rank" => 2366,
        "island_rank" => 34198,
        "z_score" => 0.3590
    ],
    [
        "name" => "Devindi Maheshika",
        "school" => "Mahamaya Balika Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "LOGIC", "result" => "S"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 2809,
        "island_rank" => 45484,
        "z_score" => 0.0033
    ],
    [
        "name" => "Yeshani Rumeshika",
        "school" => "Sri Deerananda Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "ART", "result" => "C"]
        ],
        "district_rank" => 1615,
        "island_rank" => 26569,
        "z_score" => 0.6032
    ],
    [
        "name" => "Dilmi Sandupama",
        "school" => "Rippon Girls' College, Galle",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "A"]
        ],
        "district_rank" => 810,
        "island_rank" => 12716,
        "z_score" => 1.0982
    ],
    [
        "name" => "Rashini Mindula",
        "school" => "CWW Kannangara Central College",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "MUSIC", "result" => "C"]
        ],
        "district_rank" => 3581,
        "island_rank" => 50710,
        "z_score" => 0.1736
    ],
    [
        "name" => "Ishani Pramodya",
        "school" => "Agrabodi Vidyalaya, Trincomalee",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "LOGIC", "result" => "S"],
            ["subject" => "MEDIA", "result" => "S"]
        ],
        "district_rank" => 1167,
        "island_rank" => 44705,
        "z_score" => 0.0290
    ],
    [
        "name" => "Gayanthi Saumya",
        "school" => "St. Gabrial's Girls College",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 760,
        "island_rank" => 26967,
        "z_score" => 0.5896
    ],
    [
        "name" => "Shehara Wijepala",
        "school" => "Walagamba Central College",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "ART", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 1663,
        "island_rank" => 26996,
        "z_score" => 0.5883
    ],
    [
        "name" => "Awishka Tiruni",
        "school" => "Sumana Balika Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "B"],
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "A"]
        ],
        "district_rank" => 631,
        "island_rank" => 10418,
        "z_score" => 1.1945
    ],
    [
        "name" => "Kaushalya Sandamini",
        "school" => "Naminioya Jathika Pasala",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "HOME ECONOMICS", "result" => "C"],
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 1375,
        "island_rank" => 51161,
        "z_score" => 0.1888
    ],
    [
        "name" => "Nethmi Sandupama",
        "school" => "Ananda Maithiya Madya Maha Vidyalaya",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "LOGIC", "result" => "C"],
            ["subject" => "HISTORY", "result" => "C"],
            ["subject" => "MEDIA", "result" => "B"]
        ],
        "district_rank" => 1680,
        "island_rank" => 27361,
        "z_score" => 0.5158
    ],
    [
        "name" => "Harshi Umaya Sandeepani",
        "school" => "Jinaraja Girls' College, Gampola",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "GEOGRAPHY", "result" => "S"],
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 1952,
        "island_rank" => 31382,
        "z_score" => 0.4453
    ],
    [
        "name" => "Sathindi Thilakarathna",
        "school" => "SWRD Bandaranayaka College, Kandy",
        "academic_year" => "2024 A/L",
        "results" => [
            ["subject" => "HOME ECONOMICS", "result" => "B"],
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 1619,
        "island_rank" => 26711,
        "z_score" => 0.5976
    ],
    [
        "name" => "Yenuli Mandini",
        "school" => "Gemunupura Maha Vidyalaya, Gampola",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "DANCING", "result" => "A"],
            ["subject" => "DRAMA", "result" => "B"]
        ],
        "district_rank" => 94,
        "island_rank" => 5057,
        "z_score" => 1.4748
    ],
    [
        "name" => "Piyumini Gayandi",
        "school" => "Jayahela National School, Kothmale",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "MUSIC", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"]
        ],
        "district_rank" => 182,
        "island_rank" => 7946,
        "z_score" => 1.3089
    ],
    [
        "name" => "Ashini Uthsari",
        "school" => "Delta Gemunupura Maha Vidyalaya",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "DANCING", "result" => "A"],
            ["subject" => "DRAMA", "result" => "A"]
        ],
        "district_rank" => 190,
        "island_rank" => 8345,
        "z_score" => 1.2883
    ],
    [
        "name" => "Oshadhi Kesara Fernando",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "SINHALA", "result" => "A"],
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "HISTORY", "result" => "B"]
        ],
        "district_rank" => 556,
        "island_rank" => 9262,
        "z_score" => 1.2418
    ],
    [
        "name" => "A.H.M.D.Sithlini Gamlath Wijayarathne",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "ECONOMICS", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "C"]
        ],
        "district_rank" => 1390,
        "island_rank" => 22758,
        "z_score" => 0.7004
    ],
    [
        "name" => "Senithi Sehansa",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "ICT", "result" => "S"],
            ["subject" => "JAPANESE", "result" => "S"]
        ],
        "district_rank" => 1929,
        "island_rank" => 33074,
        "z_score" => 0.3556
    ],
    [
        "name" => "Thurya Dananjani Sandunika",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "DRAMA", "result" => "S"]
        ],
        "district_rank" => 1638,
        "island_rank" => 45566,
        "z_score" => 0.0530
    ],
    [
        "name" => "Kawya Dewmini",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "AGRICULTURE", "result" => "A"],
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "HISTORY", "result" => "A"]
        ],
        "district_rank" => 51,
        "island_rank" => 795,
        "z_score" => 1.9176
    ],
    [
        "name" => "Rohan Rukshan",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "HOME ECONOMICS", "result" => "A"],
            ["subject" => "POLITICAL SCIENCE", "result" => "B"]
        ],
        "district_rank" => 304,
        "island_rank" => 5215,
        "z_score" => 1.4640
    ],
    [
        "name" => "Raniki Shamalka Fernando",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "DRAMA", "result" => "A"],
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "JAPANESE", "result" => "B"]
        ],
        "district_rank" => 251,
        "island_rank" => 4250,
        "z_score" => 1.5291
    ],
    [
        "name" => "Senulya Marasingha",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "A"],
            ["subject" => "JAPANESE", "result" => "A"]
        ],
        "district_rank" => 18,
        "island_rank" => 109,
        "z_score" => 2.2521
    ],
    [
        "name" => "Tharaka Randima Ransinghe",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "DRAMA", "result" => "B"],
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "ART", "result" => "B"]
        ],
        "district_rank" => 801,
        "island_rank" => 13451,
        "z_score" => 1.0553
    ],
    [
        "name" => "Dhanodya Ahinsani Wijesundara",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "POLITICAL SCIENCE", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "B"]
        ],
        "district_rank" => 695,
        "island_rank" => 7282,
        "z_score" => 1.3439
    ],
    [
        "name" => "Nishal Pragith Bandara",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "POLITICAL SCIENCE", "result" => "A"],
            ["subject" => "DANCING", "result" => "A"]
        ],
        "district_rank" => 56,
        "island_rank" => 3349,
        "z_score" => 1.6058
    ],
    [
        "name" => "Lihini Kawya",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "GEOGRAPHY", "result" => "A"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 624,
        "island_rank" => 10456,
        "z_score" => 1.1854
    ],
    [
        "name" => "Tharushi Himasha",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "C"]
        ],
        "district_rank" => 1187,
        "island_rank" => 35344,
        "z_score" => 0.2823
    ],
    [
        "name" => "Kawindya Dewmini Kumarasinghe",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "ECONOMICS", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "B"]
        ],
        "district_rank" => 1054,
        "island_rank" => 17632,
        "z_score" => 0.8887
    ],
    [
        "name" => "Indeewari Saubhagya Kumari",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "HOME ECONOMICS", "result" => "A"],
            ["subject" => "DANCING", "result" => "A"]
        ],
        "district_rank" => 3,
        "island_rank" => 565,
        "z_score" => 1.9798
    ],
    [
        "name" => "Ayesha Damayanthi Manike",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "POLITICAL SCIENCE", "result" => "B"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 228,
        "island_rank" => 9817,
        "z_score" => 1.2149
    ],
    [
        "name" => "Nethmi Kaveesha Thilakarathne",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "BUDDHIST CULTURE", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "B"]
        ],
        "district_rank" => 34,
        "island_rank" => 2445,
        "z_score" => 1.6877
    ],
    [
        "name" => "Chathuni Isurika Rajapaksha",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "HISTORY", "result" => "B"],
            ["subject" => "ART", "result" => "B"]
        ],
        "district_rank" => 684,
        "island_rank" => 11001,
        "z_score" => 1.1616
    ],
    [
        "name" => "Nethmi Tharangi Kaushalya",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "B"],
            ["subject" => "GEOGRAPHY", "result" => "B"]
        ],
        "district_rank" => 902,
        "island_rank" => 15208,
        "z_score" => 0.9848
    ],
    [
        "name" => "Thakshila Manel Kumari",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "BUDDHIST CULTURE", "result" => "B"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 1507,
        "island_rank" => 24452,
        "z_score" => 0.6414
    ],
    [
        "name" => "Ishani Umeshika Ranaweera",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "C"]
        ],
        "district_rank" => 1302,
        "island_rank" => 38146,
        "z_score" => 0.1929
    ],
    [
        "name" => "Nadun Madusanka",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "HISTORY", "result" => "B"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 657,
        "island_rank" => 22240,
        "z_score" => 0.7182
    ],
    [
        "name" => "Madusha Sandakirani",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "LOGIC", "result" => "B"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 627,
        "island_rank" => 10502,
        "z_score" => 1.1825
    ],
    [
        "name" => "Oshadi Vihansa Mandakini",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "DANCING", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 1532,
        "island_rank" => 24633,
        "z_score" => 0.6348
    ],
    [
        "name" => "Samindi Malsha Aththanayaka",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "POLITICAL SCIENCE", "result" => "A"],
            ["subject" => "LOGIC", "result" => "C"]
        ],
        "district_rank" => 440,
        "island_rank" => 9685,
        "z_score" => 1.2216
    ],
    [
        "name" => "Nethmi Heshani",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "GEOGRAPHY", "result" => "C"],
            ["subject" => "ICT", "result" => "S"]
        ],
        "district_rank" => 1795,
        "island_rank" => 28357,
        "z_score" => 0.5109
    ],
    [
        "name" => "Dilini Nimesha Madhushani",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "HOME ECONOMICS", "result" => "A"],
            ["subject" => "DANCING", "result" => "B"]
        ],
        "district_rank" => 830,
        "island_rank" => 8708,
        "z_score" => 1.2684
    ],
    [
        "name" => "Kawya Gauthami Buddhika",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "S"]
        ],
        "district_rank" => 1009,
        "island_rank" => 131050,
        "z_score" => 0.4214
    ],
    [
        "name" => "Merari Kristina",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "HOME ECONOMICS", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"]
        ],
        "district_rank" => 2254,
        "island_rank" => 42321,
        "z_score" => 0.0584
    ],
    [
        "name" => "Samuni Lakshika Charunayani",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "B"],
            ["subject" => "AGRICULTURE", "result" => "S"]
        ],
        "district_rank" => 1012,
        "island_rank" => 31089,
        "z_score" => 0.4202
    ],
    [
        "name" => "Bhagya Sewmini",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "B"]
        ],
        "district_rank" => 63,
        "island_rank" => 3598,
        "z_score" => 1.5830
    ],
    [
        "name" => "Tisali Senulya Marasinghe",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "JAPANESE", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "A"]
        ],
        "district_rank" => 18,
        "island_rank" => 109,
        "z_score" => 2.2521
    ],
    [
        "name" => "Nethmi Arundhi Gunarathna",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "SINHALA", "result" => "C"],
            ["subject" => "AGRICULTURE", "result" => "S"]
        ],
        "district_rank" => 1359,
        "island_rank" => 39260,
        "z_score" => 0.1563
    ],
    [
        "name" => "Bhagya Sewwandi",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "POLITICAL SCIENCE", "result" => "A"],
            ["subject" => "SINHALA", "result" => "B"]
        ],
        "district_rank" => 735,
        "island_rank" => 12365,
        "z_score" => 1.0997
    ],
    [
        "name" => "Abdulla Sham Shiyabdeen Amal Deiyanna",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "S"],
            ["subject" => "SINHALA", "result" => "B"],
            ["subject" => "HOME ECONOMICS", "result" => "C"]
        ],
        "district_rank" => 2302,
        "island_rank" => 43202,
        "z_score" => 0.0291
    ],
    [
        "name" => "Samadhi Thathsarani Senevirathna",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "B"],
            ["subject" => "SINHALA", "result" => "A"],
            ["subject" => "POLITICAL SCIENCE", "result" => "B"]
        ],
        "district_rank" => 208,
        "island_rank" => 8778,
        "z_score" => 1.2651
    ],
    [
        "name" => "Ushani Chalukya Priyantha",
        "school" => "Not Specified",
        "academic_year" => "2025 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "C"],
            ["subject" => "LOGIC", "result" => "S"],
            ["subject" => "GEOGRAPHY", "result" => "S"]
        ],
        "district_rank" => 2706,
        "island_rank" => 41229,
        "z_score" => 0.0943
    ],
    [
        "name" => "A.G.Sasini Kawya Darmarathna",
        "school" => "Not Specified",
        "academic_year" => "2026 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "A"]
        ],
        "district_rank" => 11,
        "island_rank" => 734,
        "z_score" => 1.9353
    ],
    [
        "name" => "L. Kithruwan Mallika Arachchi",
        "school" => "Not Specified",
        "academic_year" => "2026 A/L",
        "results" => [
            ["subject" => "HISTORY", "result" => "A"],
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"]
        ],
        "district_rank" => 218,
        "island_rank" => 3821,
        "z_score" => 1.5644
    ],
    [
        "name" => "P.K.M.Nuwan Prabod Panchawatta",
        "school" => "Not Specified",
        "academic_year" => "2026 A/L",
        "results" => [
            ["subject" => "MEDIA", "result" => "A"],
            ["subject" => "SINHALA", "result" => "A"],
            ["subject" => "GEOGRAPHY", "result" => "A"]
        ],
        "district_rank" => 93,
        "island_rank" => 1989,
        "z_score" => 1.7368
    ]
];

$password_hash = password_hash('admin1234', PASSWORD_DEFAULT);
$logs = [];
$success_count = 0;
$error_count = 0;

$base_index_number = 1234567890;

foreach ($students as $index => $s) {
    $counter = $index + 1;
    $full_name = trim($s['name']);
    $parts = explode(' ', $full_name, 2);
    $first_name = $parts[0] ?? $full_name;
    $second_name = $parts[1] ?? '';
    
    // Generate clean email
    $email_slug = preg_replace('/[^a-z0-9]/', '', strtolower($full_name));
    $email = "al_student_" . $counter . "_" . substr($email_slug, 0, 10) . "@lernerr.lk";
    
    // Generate unique User ID
    $user_id = sprintf("STU_AL_%04d", $counter);
    $index_no = (string)($base_index_number + $counter);
    $school_text = $s['school'] ?? 'Not Specified';
    $district = extract_district($school_text);
    $exam_year = extract_exam_year($s['academic_year'] ?? '2024');
    $stream = 'Arts'; // Stream for these subjects

    // De-duplicate subject names for this student
    $subj_list = [];
    foreach ($s['results'] as $res_item) {
        $c_name = clean_subject_name($res_item['subject']);
        $g_val = strtoupper(trim($res_item['result']));
        // If result is not valid grade letter, default to C
        if (!in_array($g_val, ['A', 'B', 'C', 'S', 'F'])) {
            $g_val = 'C';
        }
        $subj_list[] = ['subject' => $c_name, 'result' => $g_val];
    }

    // Ensure 3 distinct subject names
    $seen_subjects = [];
    for ($i = 0; $i < count($subj_list); $i++) {
        $name = $subj_list[$i]['subject'];
        if (isset($seen_subjects[$name])) {
            $seen_subjects[$name]++;
            $subj_list[$i]['subject'] = $name . ' ' . $seen_subjects[$name];
        } else {
            $seen_subjects[$name] = 1;
        }
    }

    // Fallback if less than 3 subjects
    while (count($subj_list) < 3) {
        $subj_list[] = ['subject' => 'GENERAL KNOWLEDGE ' . (count($subj_list) + 1), 'result' => 'C'];
    }

    $subject_1 = $subj_list[0]['subject'];
    $result_1 = $subj_list[0]['result'];
    $subject_2 = $subj_list[1]['subject'];
    $result_2 = $subj_list[1]['result'];
    $subject_3 = $subj_list[2]['subject'];
    $result_3 = $subj_list[2]['result'];

    $district_rank = $s['district_rank'] !== null ? (int)$s['district_rank'] : null;
    $island_rank = $s['island_rank'] !== null ? (int)$s['island_rank'] : null;
    $z_score = $s['z_score'] !== null ? (float)$s['z_score'] : null;

    $conn->begin_transaction();
    try {
        // 1. Insert or Update User
        $stmt_user = $conn->prepare("INSERT INTO users 
            (user_id, email, password, role, first_name, second_name, school_name, exam_year, district, approved, status, registering_date) 
            VALUES (?, ?, ?, 'student', ?, ?, ?, ?, ?, 1, 1, CURDATE())
            ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), second_name = VALUES(second_name), school_name = VALUES(school_name)");
        if (!$stmt_user) {
            throw new Exception("Users prepare error: " . $conn->error);
        }
        $stmt_user->bind_param("ssssssis", $user_id, $email, $password_hash, $first_name, $second_name, $school_text, $exam_year, $district);
        $stmt_user->execute();
        $stmt_user->close();

        // 2. Insert or Update AL Exam Submissions
        $query_sub = "INSERT INTO al_exam_submissions 
            (student_id, subject_1, result_1, subject_2, result_2, subject_3, result_3, index_number, district, stream, agreed_to_publish, results_submitted_at, exam_year, district_rank, island_rank, z_score) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                subject_1 = VALUES(subject_1), result_1 = VALUES(result_1),
                subject_2 = VALUES(subject_2), result_2 = VALUES(result_2),
                subject_3 = VALUES(subject_3), result_3 = VALUES(result_3),
                index_number = VALUES(index_number), district = VALUES(district),
                stream = VALUES(stream), agreed_to_publish = 1, results_submitted_at = NOW(),
                exam_year = VALUES(exam_year), district_rank = VALUES(district_rank),
                island_rank = VALUES(island_rank), z_score = VALUES(z_score)";
        
        $stmt_sub = $conn->prepare($query_sub);
        if (!$stmt_sub) {
            throw new Exception("Submissions prepare error: " . $conn->error);
        }
        $stmt_sub->bind_param("ssssssssssiiid", 
            $user_id, 
            $subject_1, $result_1, 
            $subject_2, $result_2, 
            $subject_3, $result_3, 
            $index_no, $district, $stream, 
            $exam_year, $district_rank, $island_rank, $z_score
        );
        $stmt_sub->execute();
        $stmt_sub->close();

        $conn->commit();
        $success_count++;
        $logs[] = [
            'status' => 'success',
            'msg' => "Successfully added <strong>{$full_name}</strong> (User ID: {$user_id}, District: {$district}, Year: {$exam_year})"
        ];
    } catch (Exception $e) {
        $conn->rollback();
        $error_count++;
        $logs[] = [
            'status' => 'error',
            'msg' => "Failed adding <strong>{$full_name}</strong>: " . $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>A/L Batch Results Inserter | Lernerr.LK</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen p-6 md:p-12">

    <div class="max-w-4xl mx-auto bg-slate-800 border border-slate-700 rounded-3xl p-8 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-700 pb-6 mb-6">
            <div>
                <h1 class="text-3xl font-extrabold text-white">A/L Results Database Importer</h1>
                <p class="text-slate-400 text-sm mt-1">Populating student users and published A/L examination results</p>
            </div>
            <a href="dashboard/ALDetails.php" class="bg-red-600 hover:bg-red-700 text-white font-bold px-5 py-3 rounded-xl transition-all shadow-lg text-sm">
                View AL Results Portal &rarr;
            </a>
        </div>

        <!-- Summary Bar -->
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="bg-slate-900/60 border border-slate-700 p-5 rounded-2xl text-center">
                <span class="text-xs uppercase font-extrabold text-slate-400">Total Records</span>
                <p class="text-3xl font-black text-white mt-1"><?php echo count($students); ?></p>
            </div>
            <div class="bg-emerald-950/60 border border-emerald-800/50 p-5 rounded-2xl text-center">
                <span class="text-xs uppercase font-extrabold text-emerald-400">Successfully Saved</span>
                <p class="text-3xl font-black text-emerald-400 mt-1"><?php echo $success_count; ?></p>
            </div>
            <div class="bg-rose-950/60 border border-rose-800/50 p-5 rounded-2xl text-center">
                <span class="text-xs uppercase font-extrabold text-rose-400">Errors</span>
                <p class="text-3xl font-black text-rose-400 mt-1"><?php echo $error_count; ?></p>
            </div>
        </div>

        <!-- Execution Logs -->
        <h2 class="text-lg font-bold text-slate-200 mb-4">Execution Status Log</h2>
        <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 max-h-[480px] overflow-y-auto space-y-2 text-xs font-mono">
            <?php foreach ($logs as $log): ?>
                <?php if ($log['status'] === 'success'): ?>
                    <div class="p-2.5 rounded-lg bg-emerald-950/40 border border-emerald-900/40 text-emerald-300">
                        ✓ <?php echo $log['msg']; ?>
                    </div>
                <?php else: ?>
                    <div class="p-2.5 rounded-lg bg-rose-950/40 border border-rose-900/40 text-rose-300">
                        ✗ <?php echo $log['msg']; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="mt-8 pt-6 border-t border-slate-700 flex justify-between items-center text-xs text-slate-400">
            <span>All default user passwords set to: <strong class="text-white">admin1234</strong></span>
            <a href="dashboard/ALDetails.php" class="text-red-400 hover:underline font-bold">Go to A/L Results Page &rarr;</a>
        </div>
    </div>

</body>
</html>
