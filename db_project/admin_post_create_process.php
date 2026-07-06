<?php
session_start();

// 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    die("<script>alert('관리자만 접근 가능합니다.'); location.href='login.php';</script>");
}

// POST 요청 체크
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("<script>alert('잘못된 접근입니다.'); location.href='admin_post_create.php';</script>");
}

// DB 연결
include 'db_conn.php';

if (!$conn) {
    die("<script>alert('DB 연결에 실패했습니다.'); location.href='admin_post_create.php';</script>");
}

// 기본 정보 받기
$post_title = $_POST['post_title'];
$type_id = $_POST['type_id'];
$post_data = $_POST['post_data'];
$start_date = $_POST['start_date'];
$finish_date = $_POST['finish_date'];
$post_url = !empty($_POST['post_url']) ? $_POST['post_url'] : null;
$details = isset($_POST['details']) ? $_POST['details'] : [];
$criteria_list = isset($_POST['criteria']) ? $_POST['criteria'] : [];

// 유효성 검사
if (empty($post_title) || empty($type_id) || empty($post_data) || empty($start_date) || empty($finish_date)) {
    die("<script>alert('필수 항목을 모두 입력해주세요.'); history.back();</script>");
}

if (count($details) == 0) {
    die("<script>alert('최소 1개의 주택 상세 정보를 추가해주세요.'); history.back();</script>");
}

if (count($criteria_list) == 0) {
    die("<script>alert('최소 1개의 자격 요건을 추가해주세요.'); history.back();</script>");
}

try {
    // ============================================================
    // STEP 1: HousingPost 입력
    // [수정] post_id와 seq_post_id.NEXTVAL을 제거했습니다.
    // DB가 자동으로 생성한 ID를 RETURNING 절로 받아옵니다.
    // ============================================================
    $sql_post = "INSERT INTO HousingPost (type_id, post_title, post_url, POST_DATE, START_DATE, FINISH_DATE) 
                 VALUES (:type_id, :post_title, :post_url, 
                         TO_DATE(:post_data, 'YYYY-MM-DD'), 
                         TO_DATE(:start_date, 'YYYY-MM-DD'), 
                         TO_DATE(:finish_date, 'YYYY-MM-DD'))
                 RETURNING post_id INTO :new_post_id";
    
    $stmt_post = oci_parse($conn, $sql_post);
    oci_bind_by_name($stmt_post, ':type_id', $type_id);
    oci_bind_by_name($stmt_post, ':post_title', $post_title);
    oci_bind_by_name($stmt_post, ':post_url', $post_url);
    oci_bind_by_name($stmt_post, ':post_data', $post_data);
    oci_bind_by_name($stmt_post, ':start_date', $start_date);
    oci_bind_by_name($stmt_post, ':finish_date', $finish_date);
    
    $new_post_id = 0;
    // 생성된 ID를 변수에 담습니다.
    oci_bind_by_name($stmt_post, ':new_post_id', $new_post_id, 32);
    
    $result = @oci_execute($stmt_post, OCI_NO_AUTO_COMMIT);
    
    if (!$result) {
        $e = oci_error($stmt_post);
        throw new Exception('HousingPost 입력 실패: ' . $e['message']);
    }
    
    // ============================================================
    // STEP 2: 주택 상세 정보 입력 (HouseDetail)
    // detail_id 제외 (DB 자동 생성)
    // ============================================================
    foreach ($details as $detail) {
        $complex_name = $detail['complex_name'];
        $house_type_name = $detail['house_type_name'];
        $exclusive_area = !empty($detail['exclusive_area']) ? $detail['exclusive_area'] : null;
        $deposit = !empty($detail['deposit']) ? $detail['deposit'] : null;
        $monthly_rent = !empty($detail['monthly_rent']) ? $detail['monthly_rent'] : null;
        $region_id = !empty($detail['region_id']) ? $detail['region_id'] : null;
        
        $sql_detail = "INSERT INTO HouseDetail (post_id, complex_name, house_type_name, AREA, deposit, monthly_rent, region_id) 
                       VALUES (:post_id, :complex_name, :house_type_name, :exclusive_area, :deposit, :monthly_rent, :region_id)";
        
        $stmt_detail = oci_parse($conn, $sql_detail);
        oci_bind_by_name($stmt_detail, ':post_id', $new_post_id);
        oci_bind_by_name($stmt_detail, ':complex_name', $complex_name);
        oci_bind_by_name($stmt_detail, ':house_type_name', $house_type_name);
        oci_bind_by_name($stmt_detail, ':exclusive_area', $exclusive_area);
        oci_bind_by_name($stmt_detail, ':deposit', $deposit);
        oci_bind_by_name($stmt_detail, ':monthly_rent', $monthly_rent);
        oci_bind_by_name($stmt_detail, ':region_id', $region_id);
        
        $result = @oci_execute($stmt_detail, OCI_NO_AUTO_COMMIT);
        
        if (!$result) {
            $e = oci_error($stmt_detail);
            throw new Exception('HouseDetail 입력 실패: ' . $e['message']);
        }
    }
    
    // ============================================================
    // STEP 3: 자격 요건 입력 (EligibilityCriteria)
    // eligibility_id 제외 (DB 자동 생성)
    // ============================================================
    foreach ($criteria_list as $criteria) {
        $criteria_name = $criteria['criteria_name'];
        $is_sejong_resident = isset($criteria['is_sejong_resident']) ? $criteria['is_sejong_resident'] : 0;
        $is_married = isset($criteria['is_married']) ? $criteria['is_married'] : 0;
        $is_home = isset($criteria['is_home']) ? $criteria['is_home'] : 0;
        $age_min = !empty($criteria['age_min']) ? $criteria['age_min'] : null;
        $age_max = !empty($criteria['age_max']) ? $criteria['age_max'] : null;
        $asset_limit = !empty($criteria['asset_limit']) ? $criteria['asset_limit'] : null;
        $car_limit = !empty($criteria['car_limit']) ? $criteria['car_limit'] : null;
        
        $sql_criteria = "INSERT INTO EligibilityCriteria (post_id, criteria_name, is_sejong_resident, is_married, is_home, age_min, age_max, asset_limit, car_limit) 
                         VALUES (:post_id, :criteria_name, :is_sejong_resident, :is_married, :is_home, :age_min, :age_max, :asset_limit, :car_limit)";
        
        $stmt_criteria = oci_parse($conn, $sql_criteria);
        oci_bind_by_name($stmt_criteria, ':post_id', $new_post_id);
        oci_bind_by_name($stmt_criteria, ':criteria_name', $criteria_name);
        oci_bind_by_name($stmt_criteria, ':is_sejong_resident', $is_sejong_resident);
        oci_bind_by_name($stmt_criteria, ':is_married', $is_married);
        oci_bind_by_name($stmt_criteria, ':is_home', $is_home);
        oci_bind_by_name($stmt_criteria, ':age_min', $age_min);
        oci_bind_by_name($stmt_criteria, ':age_max', $age_max);
        oci_bind_by_name($stmt_criteria, ':asset_limit', $asset_limit);
        oci_bind_by_name($stmt_criteria, ':car_limit', $car_limit);
        
        $result = @oci_execute($stmt_criteria, OCI_NO_AUTO_COMMIT);
        
        if (!$result) {
            $e = oci_error($stmt_criteria);
            throw new Exception('EligibilityCriteria 입력 실패: ' . $e['message']);
        }
    }
    
    // ============================================================
    // STEP 4: 모든 작업 성공 시 커밋
    // ============================================================
    oci_commit($conn);
    
    $detail_count = count($details);
    $criteria_count = count($criteria_list);
    
    echo "<script>
        alert('✅ 공고가 성공적으로 등록되었습니다!\\n\\n' +
              '공고 ID: {$new_post_id}\\n' +
              '주택 상세: {$detail_count}개\\n' +
              '자격 요건: {$criteria_count}개');
        location.href='admin_post_manage.php';
    </script>";
    
} catch (Exception $e) {
    if ($conn) {
        oci_rollback($conn);
    }
    $error_msg = htmlspecialchars($e->getMessage());
    echo "<script>
        alert('❌ 공고 등록 실패\\n\\n{$error_msg}');
        history.back();
    </script>";
}

if ($conn) {
    oci_close($conn);
}
?>