<?php
session_start();

// 1. 관리자 권한 및 POST 요청 체크
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    die("<script>alert('관리자만 접근 가능합니다.'); location.href='login.php';</script>");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("<script>alert('잘못된 접근입니다.'); history.back();</script>");
}

// 2. DB 연결
include 'db_conn.php';
if (!$conn) {
    die("<script>alert('DB 연결에 실패했습니다.'); history.back();</script>");
}

// 3. 데이터 수신 및 유효성 검사
$post_id = $_POST['post_id'] ?? null;

if (!$post_id) {
    die("<script>alert('수정할 공고 ID가 누락되었습니다.'); history.back();</script>");
}

$post_title = $_POST['post_title'];
$type_id = $_POST['type_id'];
$post_data = $_POST['post_data'];
$start_date = $_POST['start_date'];
$finish_date = $_POST['finish_date'];
$post_url = !empty($_POST['post_url']) ? $_POST['post_url'] : null;

$details = $_POST['details'] ?? [];
$criteria_list = $_POST['criteria'] ?? [];

if (empty($post_title) || empty($type_id) || empty($post_data) || empty($start_date) || empty($finish_date)) {
    die("<script>alert('필수 항목을 모두 입력해주세요.'); history.back();</script>");
}

try {
    // ============================================================
    // STEP 1: HousingPost (공고 기본 정보) 업데이트
    // ============================================================
    $sql_post = "UPDATE HousingPost SET 
                 type_id = :type_id, post_title = :post_title, post_url = :post_url, 
                 POST_DATE = TO_DATE(:post_data, 'YYYY-MM-DD'), 
                 START_DATE = TO_DATE(:start_date, 'YYYY-MM-DD'), 
                 FINISH_DATE = TO_DATE(:finish_date, 'YYYY-MM-DD')
                 WHERE post_id = :post_id";
    
    $stmt_post = oci_parse($conn, $sql_post);
    oci_bind_by_name($stmt_post, ':type_id', $type_id);
    oci_bind_by_name($stmt_post, ':post_title', $post_title);
    oci_bind_by_name($stmt_post, ':post_url', $post_url);
    oci_bind_by_name($stmt_post, ':post_data', $post_data);
    oci_bind_by_name($stmt_post, ':start_date', $start_date);
    oci_bind_by_name($stmt_post, ':finish_date', $finish_date);
    oci_bind_by_name($stmt_post, ':post_id', $post_id);
    
    if (!@oci_execute($stmt_post, OCI_NO_AUTO_COMMIT)) {
        $e = oci_error($stmt_post);
        throw new Exception('HousingPost 업데이트 실패: ' . $e['message']);
    }

    // ============================================================
    // STEP 2: 주택 상세 정보 (HouseDetail) 처리 (삭제, 업데이트, 삽입)
    // ============================================================
    
    // 2-A. 기존 레코드 ID 조회 (현재 DB에 있는 모든 Detail ID)
    $existing_detail_ids = [];
    $sql_exist_detail = "SELECT detail_id FROM HouseDetail WHERE post_id = :post_id";
    $stmt_exist_detail = oci_parse($conn, $sql_exist_detail);
    oci_bind_by_name($stmt_exist_detail, ':post_id', $post_id);
    oci_execute($stmt_exist_detail);
    while ($row = oci_fetch_array($stmt_exist_detail, OCI_ASSOC)) {
        $existing_detail_ids[] = $row['DETAIL_ID'];
    }
    
    // 폼에서 넘어온 Detail ID 목록
    $submitted_detail_ids = [];
    
    foreach ($details as $detail) {
        $detail_id = $detail['detail_id'];
        
        $complex_name = $detail['complex_name'];
        $house_type_name = $detail['house_type_name'];
        $exclusive_area = !empty($detail['exclusive_area']) ? $detail['exclusive_area'] : null;
        $deposit = !empty($detail['deposit']) ? $detail['deposit'] : null;
        $monthly_rent = !empty($detail['monthly_rent']) ? $detail['monthly_rent'] : null;
        $region_id = !empty($detail['region_id']) ? $detail['region_id'] : null;
        
        if ($detail_id === 'NEW') {
            // 2-B. [신규 추가]
            $sql_ins = "INSERT INTO HouseDetail (post_id, complex_name, house_type_name, AREA, deposit, monthly_rent, region_id) 
                       VALUES (:post_id, :complex_name, :house_type_name, :exclusive_area, :deposit, :monthly_rent, :region_id)";
            
            $stmt_ins = oci_parse($conn, $sql_ins);
            oci_bind_by_name($stmt_ins, ':post_id', $post_id);
            oci_bind_by_name($stmt_ins, ':complex_name', $complex_name);
            oci_bind_by_name($stmt_ins, ':house_type_name', $house_type_name);
            oci_bind_by_name($stmt_ins, ':exclusive_area', $exclusive_area);
            oci_bind_by_name($stmt_ins, ':deposit', $deposit);
            oci_bind_by_name($stmt_ins, ':monthly_rent', $monthly_rent);
            oci_bind_by_name($stmt_ins, ':region_id', $region_id);
            
            if (!@oci_execute($stmt_ins, OCI_NO_AUTO_COMMIT)) {
                $e = oci_error($stmt_ins);
                throw new Exception('HouseDetail 신규 입력 실패: ' . $e['message']);
            }
        
        } else {
            // 2-C. [기존 업데이트]
            $sql_upd = "UPDATE HouseDetail SET
                        complex_name = :complex_name, house_type_name = :house_type_name, 
                        AREA = :exclusive_area, deposit = :deposit, monthly_rent = :monthly_rent, 
                        region_id = :region_id
                        WHERE detail_id = :detail_id";
            
            $stmt_upd = oci_parse($conn, $sql_upd);
            oci_bind_by_name($stmt_upd, ':complex_name', $complex_name);
            oci_bind_by_name($stmt_upd, ':house_type_name', $house_type_name);
            oci_bind_by_name($stmt_upd, ':exclusive_area', $exclusive_area);
            oci_bind_by_name($stmt_upd, ':deposit', $deposit);
            oci_bind_by_name($stmt_upd, ':monthly_rent', $monthly_rent);
            oci_bind_by_name($stmt_upd, ':region_id', $region_id);
            oci_bind_by_name($stmt_upd, ':detail_id', $detail_id);
            
            if (!@oci_execute($stmt_upd, OCI_NO_AUTO_COMMIT)) {
                $e = oci_error($stmt_upd);
                throw new Exception('HouseDetail 업데이트 실패: ' . $e['message']);
            }
            $submitted_detail_ids[] = $detail_id;
        }
    }
    
    // 2-D. [삭제 필요 항목 처리] (폼에서 사라진 항목)
    $ids_to_delete_detail = array_diff($existing_detail_ids, $submitted_detail_ids);
    if (!empty($ids_to_delete_detail)) {
        // 배열을 쉼표로 구분된 문자열로 변환 (IN 절 사용)
        $in_clause = "'" . implode("','", $ids_to_delete_detail) . "'";
        $sql_del = "DELETE FROM HouseDetail WHERE post_id = :post_id AND detail_id IN ({$in_clause})";
        
        $stmt_del = oci_parse($conn, $sql_del);
        oci_bind_by_name($stmt_del, ':post_id', $post_id);
        
        if (!@oci_execute($stmt_del, OCI_NO_AUTO_COMMIT)) {
            $e = oci_error($stmt_del);
            throw new Exception('HouseDetail 삭제 실패: ' . $e['message']);
        }
    }


    // ============================================================
    // STEP 3: 자격 요건 (EligibilityCriteria) 처리 (삭제, 업데이트, 삽입)
    // ============================================================
    
    // 3-A. 기존 레코드 ID 조회 (현재 DB에 있는 모든 Criteria ID)
    $existing_criteria_ids = [];
    $sql_exist_crit = "SELECT eligibility_id FROM EligibilityCriteria WHERE post_id = :post_id";
    $stmt_exist_crit = oci_parse($conn, $sql_exist_crit);
    oci_bind_by_name($stmt_exist_crit, ':post_id', $post_id);
    oci_execute($stmt_exist_crit);
    while ($row = oci_fetch_array($stmt_exist_crit, OCI_ASSOC)) {
        $existing_criteria_ids[] = $row['ELIGIBILITY_ID'];
    }
    
    // 폼에서 넘어온 Criteria ID 목록
    $submitted_criteria_ids = [];
    
    foreach ($criteria_list as $criteria) {
        $eligibility_id = $criteria['eligibility_id'];
        
        // 데이터 준비 (NULL 값 처리)
        $criteria_name = $criteria['criteria_name'];
        $is_sejong_resident = $criteria['is_sejong_resident'] ?? 0;
        $is_married = $criteria['is_married'] ?? 0;
        $is_home = $criteria['is_home'] ?? 0;
        $age_min = !empty($criteria['age_min']) ? $criteria['age_min'] : null;
        $age_max = !empty($criteria['age_max']) ? $criteria['age_max'] : null;
        $asset_limit = !empty($criteria['asset_limit']) ? $criteria['asset_limit'] : null;
        $car_limit = !empty($criteria['car_limit']) ? $criteria['car_limit'] : null;
        
        if ($eligibility_id === 'NEW') {
            // 3-B. [신규 추가]
            $sql_ins = "INSERT INTO EligibilityCriteria (post_id, criteria_name, is_sejong_resident, is_married, is_home, age_min, age_max, asset_limit, car_limit) 
                         VALUES (:post_id, :criteria_name, :is_sejong_resident, :is_married, :is_home, :age_min, :age_max, :asset_limit, :car_limit)";
            
            $stmt_ins = oci_parse($conn, $sql_ins);
            oci_bind_by_name($stmt_ins, ':post_id', $post_id);
            oci_bind_by_name($stmt_ins, ':criteria_name', $criteria_name);
            oci_bind_by_name($stmt_ins, ':is_sejong_resident', $is_sejong_resident);
            oci_bind_by_name($stmt_ins, ':is_married', $is_married);
            oci_bind_by_name($stmt_ins, ':is_home', $is_home);
            oci_bind_by_name($stmt_ins, ':age_min', $age_min);
            oci_bind_by_name($stmt_ins, ':age_max', $age_max);
            oci_bind_by_name($stmt_ins, ':asset_limit', $asset_limit);
            oci_bind_by_name($stmt_ins, ':car_limit', $car_limit);
            
            if (!@oci_execute($stmt_ins, OCI_NO_AUTO_COMMIT)) {
                $e = oci_error($stmt_ins);
                throw new Exception('EligibilityCriteria 신규 입력 실패: ' . $e['message']);
            }
        
        } else {
            // 3-C. [기존 업데이트]
            $sql_upd = "UPDATE EligibilityCriteria SET
                        criteria_name = :criteria_name, is_sejong_resident = :is_sejong_resident, is_married = :is_married, 
                        is_home = :is_home, age_min = :age_min, age_max = :age_max, 
                        asset_limit = :asset_limit, car_limit = :car_limit
                        WHERE eligibility_id = :eligibility_id";
            
            $stmt_upd = oci_parse($conn, $sql_upd);
            oci_bind_by_name($stmt_upd, ':criteria_name', $criteria_name);
            oci_bind_by_name($stmt_upd, ':is_sejong_resident', $is_sejong_resident);
            oci_bind_by_name($stmt_upd, ':is_married', $is_married);
            oci_bind_by_name($stmt_upd, ':is_home', $is_home);
            oci_bind_by_name($stmt_upd, ':age_min', $age_min);
            oci_bind_by_name($stmt_upd, ':age_max', $age_max);
            oci_bind_by_name($stmt_upd, ':asset_limit', $asset_limit);
            oci_bind_by_name($stmt_upd, ':car_limit', $car_limit);
            oci_bind_by_name($stmt_upd, ':eligibility_id', $eligibility_id);
            
            if (!@oci_execute($stmt_upd, OCI_NO_AUTO_COMMIT)) {
                $e = oci_error($stmt_upd);
                throw new Exception('EligibilityCriteria 업데이트 실패: ' . $e['message']);
            }
            $submitted_criteria_ids[] = $eligibility_id;
        }
    }
    
    // 3-D. [삭제 필요 항목 처리] (폼에서 사라진 항목)
    $ids_to_delete_criteria = array_diff($existing_criteria_ids, $submitted_criteria_ids);
    if (!empty($ids_to_delete_criteria)) {
        $in_clause = "'" . implode("','", $ids_to_delete_criteria) . "'";
        $sql_del = "DELETE FROM EligibilityCriteria WHERE post_id = :post_id AND eligibility_id IN ({$in_clause})";
        
        $stmt_del = oci_parse($conn, $sql_del);
        oci_bind_by_name($stmt_del, ':post_id', $post_id);
        
        if (!@oci_execute($stmt_del, OCI_NO_AUTO_COMMIT)) {
            $e = oci_error($stmt_del);
            throw new Exception('EligibilityCriteria 삭제 실패: ' . $e['message']);
        }
    }

    // ============================================================
    // STEP 4: 모든 작업 성공 시 커밋
    // ============================================================
    oci_commit($conn);
    
    echo "<script>
        alert('✅ 공고 ID: {$post_id}가 성공적으로 수정되었습니다.');
        location.href='admin_post_manage.php';
    </script>";
    
} catch (Exception $e) {
    if ($conn) {
        oci_rollback($conn);
    }
    $error_msg = htmlspecialchars($e->getMessage());
    echo "<script>
        alert('❌ 공고 수정 실패\\n\\n{$error_msg}');
        history.back();
    </script>";
}

if ($conn) {
    oci_close($conn);
}
?>