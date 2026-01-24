<?php
header('Content-Type: application/json');

require_once 'config.php';

/**
 * Verifies Sri Lankan NIC against DOB and Gender
 * @param string $nic The NIC number (9 or 12 digits)
 * @param string $dob The Date of Birth (YYYY-MM-DD) - optional for basic validation
 * @param string $gender The Gender ('Male' or 'Female') - optional for basic validation
 * @return array Validation result with extracted data or verification status
 */
function verifySriLankanNIC($nic, $dob = null, $gender = null) {
    // 1. Basic format validation
    $nic = strtoupper(trim($nic));
    $isOldFormat = preg_match('/^[0-9]{9}[VX]$/', $nic);
    $isNewFormat = preg_match('/^[0-9]{12}$/', $nic);

    if (!$isOldFormat && !$isNewFormat) {
        return [
            'valid' => false,
            'message' => 'Invalid NIC format. Must be either 9 digits + V/X (old) or 12 digits (new)'
        ];
    }

    // 2. Extract Year and Day Value
    if ($isOldFormat) {
        $yearDigits = (int)substr($nic, 0, 2);
        $year = ($yearDigits < 30) ? 2000 + $yearDigits : 1900 + $yearDigits;
        $daysValue = (int)substr($nic, 2, 3);
    } else {
        $year = (int)substr($nic, 0, 4);
        $daysValue = (int)substr($nic, 4, 3);
    }

    // 3. Determine Gender from NIC
    // For females, 500 is added to the day value
    $nicGender = ($daysValue > 500) ? "Female" : "Male";
    
    // Adjust day value for female calculation
    $actualDays = ($nicGender === "Female") ? $daysValue - 500 : $daysValue;

    // 4. Validate day value (must be between 1 and 366)
    if ($actualDays < 1 || $actualDays > 366) {
        return [
            'valid' => false,
            'message' => 'Invalid day value in NIC. Day must be between 1 and 366.'
        ];
    }

    // 5. Calculate Date of Birth using the 366-day constant rule
    // Fixed 366-day calendar mapping (Feb is always 29)
    $monthDays = [
        1  => 31, // Jan
        2  => 29, // Feb (Always 29 in SL NIC system)
        3  => 31, // Mar
        4  => 30, // Apr
        5  => 31, // May
        6  => 30, // Jun
        7  => 31, // Jul
        8  => 31, // Aug
        9  => 30, // Sep
        10 => 31, // Oct
        11 => 30, // Nov
        12 => 31  // Dec
    ];

    $calculatedMonth = 0;
    $calculatedDate = 0;
    $tempDays = $actualDays;

    foreach ($monthDays as $month => $days) {
        if ($tempDays <= $days) {
            $calculatedMonth = $month;
            $calculatedDate = $tempDays;
            break;
        }
        $tempDays -= $days;
    }

    // Format the calculated DOB
    $nicDob = sprintf("%04d-%02d-%02d", $year, $calculatedMonth, $calculatedDate);

    // 6. If DOB and Gender provided, verify they match
    $verified = true;
    $verificationMessage = 'NIC is valid';
    
    if ($dob !== null && $gender !== null) {
        // Normalize gender input (handle lowercase/uppercase variations)
        $normalizedGender = ucfirst(strtolower($gender));
        $normalizedNicGender = ucfirst(strtolower($nicGender));
        
        // Verify Gender matches
        if (strcasecmp($normalizedNicGender, $normalizedGender) !== 0) {
            $verified = false;
            $verificationMessage = 'Gender mismatch: NIC indicates ' . $nicGender . ' but provided ' . $gender;
        }
        
        // Verify DOB matches (exact string comparison)
        if ($nicDob !== $dob) {
            $verified = false;
            if ($verificationMessage !== 'NIC is valid') {
                $verificationMessage .= '. Date of Birth mismatch: NIC indicates ' . $nicDob . ' but provided ' . $dob;
            } else {
                $verificationMessage = 'Date of Birth mismatch: NIC indicates ' . $nicDob . ' but provided ' . $dob;
            }
        }
        
        if ($verified) {
            $verificationMessage = 'NIC verified successfully against provided DOB and Gender';
        }
    }

    return [
        'valid' => $verified,
        'birth_year' => $year,
        'gender' => strtolower($nicGender), // Return lowercase for consistency
        'date_of_birth' => $nicDob,
        'month' => $calculatedMonth,
        'day' => $calculatedDate,
        'format' => $isOldFormat ? 'old' : 'new',
        'message' => $verificationMessage
    ];
}

// Get input parameters
$nic = isset($_POST['nic']) ? trim($_POST['nic']) : '';
$dob = isset($_POST['dob']) ? trim($_POST['dob']) : null;
$gender = isset($_POST['gender']) ? trim($_POST['gender']) : null;

if (empty($nic)) {
    echo json_encode(['success' => false, 'message' => 'NIC number is required']);
    exit;
}

// Normalize gender for verification (convert to capitalized form if provided)
if ($gender !== null) {
    $gender = ucfirst(strtolower($gender)); // Convert to "Male" or "Female"
}

$result = verifySriLankanNIC($nic, $dob, $gender);

if ($result['valid']) {
    echo json_encode([
        'success' => true,
        'valid' => true,
        'birth_year' => $result['birth_year'],
        'gender' => $result['gender'],
        'date_of_birth' => $result['date_of_birth'],
        'month' => $result['month'],
        'day' => $result['day'],
        'format' => $result['format'],
        'message' => $result['message']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'valid' => false,
        'message' => $result['message'],
        'birth_year' => isset($result['birth_year']) ? $result['birth_year'] : null,
        'gender' => isset($result['gender']) ? $result['gender'] : null,
        'date_of_birth' => isset($result['date_of_birth']) ? $result['date_of_birth'] : null
    ]);
}
?>
