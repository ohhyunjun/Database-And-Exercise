<?php
session_start();

// 1. 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    die("<script>alert('관리자만 접근 가능합니다.'); location.href='login.php';</script>");
}

$admin_name = isset($_SESSION['username']) ? $_SESSION['username'] : '관리자';

// 2. 파라미터 확인 및 DB 연결
if (!isset($_GET['id'])) {
    die("<script>alert('잘못된 접근입니다.'); location.href='admin_post_manage.php';</script>");
}
$post_id = $_GET['id'];

include 'db_conn.php';
if (!$conn) {
    die("<script>alert('DB 연결 실패'); location.href='admin_main.php';</script>");
}

// =================================================================
// 데이터 조회 로직 (post_detail.php 로직 활용 + 수정용 포맷팅)
// =================================================================

// A. 드롭다운용 기초 데이터 조회 (주택유형, 지역)
$sql = "SELECT type_id, type_name FROM HousingType ORDER BY type_id";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);
$housing_types = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) $housing_types[] = $row;

$sql = "SELECT region_id, dong_name FROM Region ORDER BY region_id";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);
$regions = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) $regions[] = $row;
$regions_json = json_encode($regions); // JS로 넘기기 위해 JSON 변환

// B. 공고 기본 정보 조회 (날짜 포맷 중요)
$sql_post = "SELECT P.*, 
             TO_CHAR(P.POST_DATE, 'YYYY-MM-DD') AS POST_DATE_FMT, 
             TO_CHAR(P.START_DATE, 'YYYY-MM-DD') AS START_DATE_FMT, 
             TO_CHAR(P.FINISH_DATE, 'YYYY-MM-DD') AS FINISH_DATE_FMT
             FROM HousingPost P 
             WHERE P.post_id = :pid";
$stmt_post = oci_parse($conn, $sql_post);
oci_bind_by_name($stmt_post, ':pid', $post_id);
oci_execute($stmt_post);
$post = oci_fetch_array($stmt_post, OCI_ASSOC + OCI_RETURN_NULLS);

if (!$post) {
    die("<script>alert('존재하지 않는 공고입니다.'); location.href='admin_post_manage.php';</script>");
}

// C. 주택 상세 정보 조회
$sql_details = "SELECT D.* FROM HouseDetail D WHERE D.post_id = :pid ORDER BY D.detail_id ASC";
$stmt_details = oci_parse($conn, $sql_details);
oci_bind_by_name($stmt_details, ':pid', $post_id);
oci_execute($stmt_details);
$details = [];
while($row = oci_fetch_array($stmt_details, OCI_ASSOC + OCI_RETURN_NULLS)) {
    $details[] = $row;
}
$details_json = json_encode($details);

// D. 자격 요건 조회
$sql_criteria = "SELECT * FROM EligibilityCriteria WHERE post_id = :pid ORDER BY eligibility_id ASC";
$stmt_criteria = oci_parse($conn, $sql_criteria);
oci_bind_by_name($stmt_criteria, ':pid', $post_id);
oci_execute($stmt_criteria);
$criteria_list = [];
while($row = oci_fetch_array($stmt_criteria, OCI_ASSOC + OCI_RETURN_NULLS)) {
    $criteria_list[] = $row;
}
$criteria_json = json_encode($criteria_list);

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>공고 수정 - 세종 청년주택 관리자</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* admin_post_create.php의 디자인 스타일 그대로 사용 */
        :root {
            --sejong-blue: #256CB6;
            --sejong-dark: #1a4a80;
            --sejong-light: #E6F2FF;
            --bg-color: #F5F7FA;
            --white: #FFFFFF;
            --border-color: #DDE2E5;
            --text-main: #333333;
            --text-sub: #666666;
            --danger: #FF4D4F;
            --success: #28a745;
            --sidebar-width: 260px;
            --header-height: 64px;
        }

        * { box-sizing: border-box; }
        body {
            font-family: 'Noto Sans KR', sans-serif;
            margin: 0; background-color: var(--bg-color); color: var(--text-main);
            display: flex; height: 100vh; overflow: hidden;
        }

        /* 사이드바 */
        .sidebar { width: var(--sidebar-width); background-color: var(--white); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; flex-shrink: 0; z-index: 100; }
        .brand { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; font-size: 20px; font-weight: 800; color: var(--sejong-blue); border-bottom: 1px solid var(--border-color); letter-spacing: -0.5px; }
        .brand span { color: var(--sejong-dark); margin-left: 5px; }
        
        .menu { list-style: none; padding: 20px 0; margin: 0; }
        .menu-link { display: flex; align-items: center; padding: 12px 28px; text-decoration: none; color: var(--text-sub); font-weight: 500; transition: all 0.2s; }
        .menu-link:hover { background-color: var(--sejong-light); color: var(--sejong-blue); }
        .menu-link.active { background-color: var(--sejong-blue); color: var(--white); font-weight: 700; box-shadow: 0 2px 6px rgba(37, 108, 182, 0.3); }

        /* 메인 레이아웃 */
        .main-layout { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .top-header { height: var(--header-height); background-color: var(--white); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; padding: 0 32px; }
        .page-title { font-size: 18px; font-weight: 700; color: var(--text-main); }
        .user-profile { font-size: 14px; display: flex; gap: 10px; align-items: center; color: var(--text-sub); }
        .btn-logout { border: 1px solid var(--border-color); background: white; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: var(--text-sub); transition: 0.2s; }
        .btn-logout:hover { border-color: var(--sejong-blue); color: var(--sejong-blue); }
        
        .content-scroll { flex: 1; overflow-y: auto; padding: 32px; }

        /* 폼 스타일 */
        .form-card { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 40px; max-width: 1600px; margin: 0 auto; border: 1px solid var(--border-color); }
        
        .section-header { display: flex; align-items: center; margin: 40px 0 24px 0; padding-bottom: 12px; border-bottom: 3px solid var(--sejong-blue); }
        .section-header:first-child { margin-top: 0; }
        .section-title { font-size: 22px; font-weight: 800; color: var(--sejong-blue); }
        .section-desc { font-size: 14px; color: var(--text-sub); margin-bottom: 20px; }
        
        .grid-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 24px; }
        .col-span-2 { grid-column: span 2; }
        
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; }
        .required { color: var(--danger); }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 14px; transition: 0.2s; }
        input:focus, select:focus { outline: none; border-color: var(--sejong-blue); box-shadow: 0 0 0 3px rgba(37, 108, 182, 0.1); }
        
        /* 상세 그룹 스타일 */
        .detail-group { 
            background: linear-gradient(to bottom, #F0F7FF 0%, #FFFFFF 100%);
            border: 2px solid var(--sejong-blue); 
            border-radius: 12px; padding: 32px; margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(37, 108, 182, 0.08);
        }
        .detail-group-header { display: flex; justify-content: space-between; margin-bottom: 24px; align-items: center; padding-bottom: 16px; border-bottom: 2px dashed var(--border-color); }
        .detail-label { font-size: 18px; font-weight: 800; background: var(--sejong-blue); color: white; padding: 8px 20px; border-radius: 24px; box-shadow: 0 2px 8px rgba(37, 108, 182, 0.3); }
        
        /* 자격 요건 스타일 */
        .criteria-group {
            background: #FFFFFF;
            border: 2px solid #60A5FA;
            border-radius: 16px; padding: 36px; margin-bottom: 28px;
            box-shadow: 0 6px 20px rgba(96, 165, 250, 0.12);
            position: relative; overflow: hidden;
        }
        .criteria-group::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px; background: linear-gradient(90deg, #60A5FA, #3B82F6); }
        .criteria-group-header { display: flex; justify-content: space-between; margin-bottom: 28px; align-items: center; padding-bottom: 20px; border-bottom: 2px solid #DBEAFE; }
        .criteria-label { font-size: 19px; font-weight: 800; background: linear-gradient(135deg, #60A5FA 0%, #3B82F6 100%); color: white; padding: 10px 24px; border-radius: 30px; box-shadow: 0 4px 12px rgba(96, 165, 250, 0.3); display: flex; align-items: center; gap: 8px; }
        .criteria-content { background: linear-gradient(to bottom, #EFF6FF 0%, #FFFFFF 100%); border: 1px solid #BFDBFE; border-radius: 12px; padding: 24px; }
        
        .criteria-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .criteria-row-bottom { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        
        .checkbox-wrapper { display: flex; gap: 16px; margin-top: 8px; }
        .check-box { display: flex; align-items: center; cursor: pointer; font-size: 13px; }
        .check-box input { width: 16px; height: 16px; margin-right: 6px; accent-color: var(--sejong-blue); cursor: pointer; }
        
        .btn { padding: 12px 24px; border-radius: 6px; border: none; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s; }
        .btn-add { background: white; border: 2px dashed var(--sejong-blue); color: var(--sejong-blue); width: 100%; padding: 16px; font-size: 16px; margin-top: 16px; }
        .btn-add:hover { background: var(--sejong-light); }
        .btn-add-criteria { background: white; border: 2px dashed #60A5FA; color: #60A5FA; width: 100%; padding: 16px; font-size: 16px; margin-top: 16px; }
        .btn-add-criteria:hover { background: #EFF6FF; border-color: #3B82F6; color: #3B82F6; }
        .btn-del { background: white; border: 1px solid var(--danger); color: var(--danger); padding: 8px 16px; font-size: 13px; }
        .btn-del:hover { background: var(--danger); color: white; }
        .bottom-actions { margin-top: 60px; padding-top: 30px; border-top: 1px solid var(--border-color); display: flex; justify-content: center; gap: 16px; }
        .btn-submit { background: var(--sejong-blue); color: white; padding: 16px 60px; font-size: 18px; }
        .btn-submit:hover:not(:disabled) { background: var(--sejong-dark); transform: translateY(-2px); }
        .btn-cancel { background: #6c757d; color: white; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="brand">SEJONG<span>ADMIN</span></div>
        <ul class="menu">
            <li><a href="admin_main.php" class="menu-link">📊 대시보드</a></li>
            <li><a href="admin_post_create.php" class="menu-link">📝 공고 등록</a></li>
            <li><a href="admin_post_manage.php" class="menu-link active">📂 공고 관리</a></li>
            <li><a href="admin_users.php" class="menu-link">👥 권한 관리</a></li>
        </ul>
    </nav>

    <div class="main-layout">
        <header class="top-header">
            <div class="page-title">✏️ 공고 수정</div>
            <div class="user-profile">
                <span><?php echo htmlspecialchars($admin_name); ?>님</span>
                <button class="btn-logout" onclick="if(confirm('로그아웃 하시겠습니까?')) location.href='logout.php'">로그아웃</button>
            </div>
        </header>

        <div class="content-scroll">
            <form action="admin_post_update_process.php" method="POST" class="form-card">
                
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">

                <div class="section-header">
                    <span class="section-title">📋 기본 정보 수정</span>
                </div>
                
                <div class="grid-row">
                    <div class="col-span-2">
                        <label>공고 제목 <span class="required">*</span></label>
                        <input type="text" name="post_title" required value="<?php echo htmlspecialchars($post['POST_TITLE']); ?>">
                    </div>
                    <div>
                        <label>주택 유형 <span class="required">*</span></label>
                        <select name="type_id" required>
                            <option value="">-- 선택 --</option>
                            <?php foreach ($housing_types as $type): ?>
                            <option value="<?php echo $type['TYPE_ID']; ?>" <?php echo ($post['TYPE_ID'] == $type['TYPE_ID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['TYPE_NAME']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>공고일 <span class="required">*</span></label>
                        <input type="date" name="post_data" required value="<?php echo $post['POST_DATE_FMT']; ?>">
                    </div>
                </div>
                
                <div class="grid-row">
                    <div>
                        <label>신청 시작일 <span class="required">*</span></label>
                        <input type="date" name="start_date" required value="<?php echo $post['START_DATE_FMT']; ?>">
                    </div>
                    <div>
                        <label>신청 마감일 <span class="required">*</span></label>
                        <input type="date" name="finish_date" required value="<?php echo $post['FINISH_DATE_FMT']; ?>">
                    </div>
                    <div class="col-span-2">
                        <label>공고 URL (선택)</label>
                        <?php
                        $urlVal = $post['POST_URL'];
                        if (is_object($urlVal)) $urlVal = $urlVal->load();
                        ?>
                        <input type="url" name="post_url" placeholder="https://example.com/notice" value="<?php echo htmlspecialchars($urlVal ?? ''); ?>">
                    </div>
                </div>

                <div class="section-header">
                    <span class="section-title">🏠 주택 상세 정보</span>
                </div>
                <p class="section-desc">기존 정보를 수정하거나 새로운 상세 정보를 추가할 수 있습니다.</p>
                
                <div id="detailContainer"></div>
                <button type="button" class="btn btn-add" onclick="addDetail()">➕ 주택 상세 추가</button>

                <div class="section-header">
                    <span class="section-title">📌 자격 요건</span>
                </div>
                <p class="section-desc">기존 요건을 수정하거나 추가할 수 있습니다.</p>
                
                <div id="criteriaContainer"></div>
                <button type="button" class="btn btn-add-criteria" onclick="addCriteria()">➕ 자격 요건 추가</button>

                <div class="bottom-actions">
                    <button type="button" class="btn btn-cancel" onclick="location.href='admin_post_manage.php'">취소</button>
                    <button type="submit" class="btn btn-submit">✅ 수정 완료</button>
                </div>
            </form>
        </div>
    </div>

    <template id="tmpl-detail">
        <div class="detail-group">
            <input type="hidden" name="details[P_INDEX][detail_id]" class="detail-id-input" value="NEW">
            <div class="detail-group-header">
                <span class="detail-label">🏘️ 주택 상세 <span class="detail-id-display"></span></span>
                <button type="button" class="btn btn-del" onclick="removeDetail(this)">🗑️ 삭제</button>
            </div>
            
            <div class="grid-row">
                <div class="col-span-2">
                    <label>단지명 <span class="required">*</span></label>
                    <input type="text" name="details[P_INDEX][complex_name]" class="input-complex" required>
                </div>
                <div>
                    <label>주택형 (평형/면적) <span class="required">*</span></label>
                    <input type="text" name="details[P_INDEX][house_type_name]" class="input-housetype" required>
                </div>
                <div>
                    <label>전용면적 (㎡)</label>
                    <input type="number" step="0.01" name="details[P_INDEX][exclusive_area]" class="input-area">
                </div>
            </div>
            
            <div class="grid-row">
                <div>
                    <label>보증금 (원)</label>
                    <input type="number" name="details[P_INDEX][deposit]" class="input-deposit">
                </div>
                <div>
                    <label>월세 (원)</label>
                    <input type="number" name="details[P_INDEX][monthly_rent]" class="input-rent">
                </div>
                <div>
                    <label>지역</label>
                    <select name="details[P_INDEX][region_id]" class="region-select">
                        <option value="">-- 선택 --</option>
                    </select>
                </div>
                <div></div>
            </div>
        </div>
    </template>

    <template id="tmpl-criteria">
        <div class="criteria-group">
            <input type="hidden" name="criteria[C_INDEX][eligibility_id]" class="criteria-id-input" value="NEW">
            <div class="criteria-group-header">
                <span class="criteria-label">
                    <span>✅</span>
                    <span>자격 요건 <span class="criteria-id-display"></span></span>
                </span>
                <button type="button" class="btn btn-del" onclick="removeCriteria(this)">🗑️ 삭제</button>
            </div>
            
            <div class="criteria-content">
                <div class="criteria-row">
                    <div>
                        <label>조건명 <span class="required">*</span></label>
                        <input type="text" name="criteria[C_INDEX][criteria_name]" class="input-name" required>
                    </div>
                    <div>
                        <label>세종시 거주자</label>
                        <div class="checkbox-wrapper">
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_sejong_resident]" value="1" class="radio-sejong-1"> 필수</label>
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_sejong_resident]" value="0" class="radio-sejong-0" checked> 무관</label>
                        </div>
                    </div>
                    <div>
                        <label>결혼 여부</label>
                        <div class="checkbox-wrapper">
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_married]" value="1" class="radio-married-1"> 필수</label>
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_married]" value="0" class="radio-married-0" checked> 무관</label>
                        </div>
                    </div>
                    <div>
                        <label>집 소유 여부</label>
                        <div class="checkbox-wrapper">
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_home]" value="1" class="radio-home-1"> 유주택</label>
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_home]" value="0" class="radio-home-0" checked> 무주택</label>
                        </div>
                    </div>
                </div>
                
                <div class="criteria-row-bottom">
                    <div>
                        <label>연령 최소</label>
                        <input type="number" name="criteria[C_INDEX][age_min]" class="input-age-min">
                    </div>
                    <div>
                        <label>연령 최대</label>
                        <input type="number" name="criteria[C_INDEX][age_max]" class="input-age-max">
                    </div>
                    <div>
                        <label>자산한도 (원)</label>
                        <input type="number" name="criteria[C_INDEX][asset_limit]" class="input-asset">
                    </div>
                    <div>
                        <label>차량가액 (원)</label>
                        <input type="number" name="criteria[C_INDEX][car_limit]" class="input-car">
                    </div>
                </div>
            </div>
        </div>
    </template>

    <script>
        // PHP에서 가져온 데이터를 JS 변수로 변환
        const regionsData = <?php echo $regions_json; ?>;
        const existingDetails = <?php echo $details_json; ?>;
        const existingCriteria = <?php echo $criteria_json; ?>;

        let detailIndex = 0;
        let criteriaIndex = 0;

        // 주택 상세 추가 함수 (데이터가 있으면 채워넣음)
        function addDetail(data = null) {
            const tmpl = document.getElementById('tmpl-detail').innerHTML;
            const html = tmpl.replace(/P_INDEX/g, detailIndex);
            const div = document.createElement('div');
            div.innerHTML = html;
            const newBlock = div.firstElementChild;
            document.getElementById('detailContainer').appendChild(newBlock);
            
            // 지역 옵션 추가
            const regionSelect = newBlock.querySelector('.region-select');
            regionsData.forEach(region => {
                const option = document.createElement('option');
                option.value = region.REGION_ID;
                option.textContent = region.DONG_NAME;
                regionSelect.appendChild(option);
            });
            
            // 데이터 매핑 (수정 모드일 때)
            if (data) {
                newBlock.querySelector('.detail-id-input').value = data.DETAIL_ID;
                newBlock.querySelector('.detail-id-display').textContent = '(ID: ' + data.DETAIL_ID + ')';
                newBlock.querySelector('.input-complex').value = data.COMPLEX_NAME;
                newBlock.querySelector('.input-housetype').value = data.HOUSE_TYPE_NAME;
                newBlock.querySelector('.input-area').value = data.AREA;
                newBlock.querySelector('.input-deposit').value = data.DEPOSIT;
                newBlock.querySelector('.input-rent').value = data.MONTHLY_RENT;
                
                if(data.REGION_ID) {
                    regionSelect.value = data.REGION_ID;
                }
            } else {
                newBlock.querySelector('.detail-id-display').textContent = '(신규)';
            }
            
            detailIndex++;
        }

        // 자격 요건 추가 함수 (데이터가 있으면 채워넣음)
        function addCriteria(data = null) {
            const tmpl = document.getElementById('tmpl-criteria').innerHTML;
            const html = tmpl.replace(/C_INDEX/g, criteriaIndex);
            const div = document.createElement('div');
            div.innerHTML = html;
            const newBlock = div.firstElementChild;
            document.getElementById('criteriaContainer').appendChild(newBlock);

            // 데이터 매핑 (수정 모드일 때)
            if (data) {
                newBlock.querySelector('.criteria-id-input').value = data.ELIGIBILITY_ID;
                newBlock.querySelector('.criteria-id-display').textContent = '(ID: ' + data.ELIGIBILITY_ID + ')';
                newBlock.querySelector('.input-name').value = data.CRITERIA_NAME;
                newBlock.querySelector('.input-age-min').value = data.AGE_MIN;
                newBlock.querySelector('.input-age-max').value = data.AGE_MAX;
                newBlock.querySelector('.input-asset').value = data.ASSET_LIMIT;
                newBlock.querySelector('.input-car').value = data.CAR_LIMIT;
                
                // 라디오 버튼 체크
                if (data.IS_SEJONG_RESIDENT == 1) newBlock.querySelector('.radio-sejong-1').checked = true;
                else newBlock.querySelector('.radio-sejong-0').checked = true;

                if (data.IS_MARRIED == 1) newBlock.querySelector('.radio-married-1').checked = true;
                else newBlock.querySelector('.radio-married-0').checked = true;

                if (data.IS_HOME == 1) newBlock.querySelector('.radio-home-1').checked = true;
                else newBlock.querySelector('.radio-home-0').checked = true;
            } else {
                newBlock.querySelector('.criteria-id-display').textContent = '(신규)';
            }

            criteriaIndex++;
        }

        function removeDetail(btn) {
            if(confirm('이 항목을 삭제하시겠습니까? (저장 시 DB에서도 삭제됩니다)')) {
                btn.closest('.detail-group').remove();
            }
        }

        function removeCriteria(btn) {
            if(confirm('이 항목을 삭제하시겠습니까? (저장 시 DB에서도 삭제됩니다)')) {
                btn.closest('.criteria-group').remove();
            }
        }

        // 페이지 로드 시 기존 데이터 불러오기
        window.onload = function() {
            // 기존 주택 상세 로드
            if (existingDetails && existingDetails.length > 0) {
                existingDetails.forEach(item => addDetail(item));
            } else {
                addDetail(); // 없으면 빈 칸 하나 생성
            }

            // 기존 자격 요건 로드
            if (existingCriteria && existingCriteria.length > 0) {
                existingCriteria.forEach(item => addCriteria(item));
            } else {
                addCriteria(); // 없으면 빈 칸 하나 생성
            }
        };
    </script>

</body>
</html>