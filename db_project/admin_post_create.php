<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    echo "<script>alert('관리자만 접근 가능합니다.'); location.href='login.php';</script>";
    exit();
}

$admin_name = isset($_SESSION['username']) ? $_SESSION['username'] : '관리자';

// DB 연결
include 'db_conn.php';

if (!$conn) {
    die("<script>alert('DB 연결 실패'); location.href='admin_main.php';</script>");
}

// 주택 유형 조회
$sql = "SELECT type_id, type_name FROM HousingType ORDER BY type_id";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);
$housing_types = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) {
    $housing_types[] = $row;
}

// 지역 목록 조회
$sql = "SELECT region_id, dong_name FROM Region ORDER BY region_id";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);
$regions = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) {
    $regions[] = $row;
}

$regions_json = json_encode($regions);
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>공고 등록 - 세종 청년주택 관리자</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* [Color Palette: Sejong City Blue Theme] */
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

        /* === 사이드바 === */
        .sidebar { width: var(--sidebar-width); background-color: var(--white); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; flex-shrink: 0; z-index: 100; }
        .brand { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; font-size: 20px; font-weight: 800; color: var(--sejong-blue); border-bottom: 1px solid var(--border-color); letter-spacing: -0.5px; }
        .brand span { color: var(--sejong-dark); margin-left: 5px; }
        
        .menu { list-style: none; padding: 20px 0; margin: 0; }
        .menu-link { display: flex; align-items: center; padding: 12px 28px; text-decoration: none; color: var(--text-sub); font-weight: 500; transition: all 0.2s; }
        .menu-link:hover { background-color: var(--sejong-light); color: var(--sejong-blue); }
        .menu-link.active { background-color: var(--sejong-blue); color: var(--white); font-weight: 700; box-shadow: 0 2px 6px rgba(37, 108, 182, 0.3); }

        /* === 메인 레이아웃 === */
        .main-layout { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .top-header { height: var(--header-height); background-color: var(--white); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; padding: 0 32px; }
        .page-title { font-size: 18px; font-weight: 700; color: var(--text-main); }
        .user-profile { font-size: 14px; display: flex; gap: 10px; align-items: center; color: var(--text-sub); }
        .btn-logout { border: 1px solid var(--border-color); background: white; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: var(--text-sub); transition: 0.2s; }
        .btn-logout:hover { border-color: var(--sejong-blue); color: var(--sejong-blue); }
        
        .content-scroll { flex: 1; overflow-y: auto; padding: 32px; }

        /* === 폼 스타일 === */
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
        
        /* 주택 상세 카드 */
        .detail-group { 
            background: linear-gradient(to bottom, #F0F7FF 0%, #FFFFFF 100%);
            border: 2px solid var(--sejong-blue); 
            border-radius: 12px; 
            padding: 32px; 
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(37, 108, 182, 0.08);
        }
        .detail-group-header { display: flex; justify-content: space-between; margin-bottom: 24px; align-items: center; padding-bottom: 16px; border-bottom: 2px dashed var(--border-color); }
        .detail-label { font-size: 18px; font-weight: 800; background: var(--sejong-blue); color: white; padding: 8px 20px; border-radius: 24px; box-shadow: 0 2px 8px rgba(37, 108, 182, 0.3); }
        
        /* 자격 요건 카드 */
        .criteria-group {
            background: #FFFFFF;
            border: 2px solid #60A5FA;
            border-radius: 16px;
            padding: 36px;
            margin-bottom: 28px;
            box-shadow: 0 6px 20px rgba(96, 165, 250, 0.12);
            position: relative;
            overflow: hidden;
        }
        .criteria-group::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #60A5FA, #3B82F6);
        }
        .criteria-group-header { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 28px; 
            align-items: center; 
            padding-bottom: 20px; 
            border-bottom: 2px solid #DBEAFE;
        }
        .criteria-label { 
            font-size: 19px; 
            font-weight: 800; 
            background: linear-gradient(135deg, #60A5FA 0%, #3B82F6 100%);
            color: white; 
            padding: 10px 24px; 
            border-radius: 30px; 
            box-shadow: 0 4px 12px rgba(96, 165, 250, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .criteria-content {
            background: linear-gradient(to bottom, #EFF6FF 0%, #FFFFFF 100%);
            border: 1px solid #BFDBFE;
            border-radius: 12px;
            padding: 24px;
        }
        
        .criteria-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .criteria-row-bottom {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
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
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-cancel { background: #6c757d; color: white; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="brand">SEJONG<span>ADMIN</span></div>
        <ul class="menu">
            <li><a href="admin_main.php" class="menu-link">📊 대시보드</a></li>
            <li><a href="admin_post_create.php" class="menu-link active">📝 공고 등록</a></li>
            <li><a href="admin_post_manage.php" class="menu-link">📂 공고 관리</a></li>
            <li><a href="admin_users.php" class="menu-link">👥 권한 관리</a></li>
        </ul>
    </nav>

    <div class="main-layout">
        <header class="top-header">
            <div class="page-title">📝 공고 등록</div>
            <div class="user-profile">
                <span><?php echo htmlspecialchars($admin_name); ?>님</span>
                <button class="btn-logout" onclick="if(confirm('로그아웃 하시겠습니까?')) location.href='logout.php'">로그아웃</button>
            </div>
        </header>

        <div class="content-scroll">
            <form action="admin_post_create_process.php" method="POST" class="form-card">
                
                <div class="section-header">
                    <span class="section-title">📋 기본 정보</span>
                </div>
                
                <div class="grid-row">
                    <div class="col-span-2">
                        <label>공고 제목 <span class="required">*</span></label>
                        <input type="text" name="post_title" required placeholder="예) 2025년 세종시 청년 주택 입주자 모집">
                    </div>
                    <div>
                        <label>주택 유형 (공고 분류) <span class="required">*</span></label>
                        <select name="type_id" required>
                            <option value="">-- 선택 --</option>
                            <?php foreach ($housing_types as $type): ?>
                            <option value="<?php echo $type['TYPE_ID']; ?>"><?php echo htmlspecialchars($type['TYPE_NAME']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p style="font-size:11px; color:#888; margin-top:4px;">※ 국민임대, 행복주택, 10년 공공임대 등</p>
                    </div>
                    <div>
                        <label>공고일 <span class="required">*</span></label>
                        <input type="date" name="post_data" required>
                    </div>
                </div>
                
                <div class="grid-row">
                    <div>
                        <label>신청 시작일 <span class="required">*</span></label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div>
                        <label>신청 마감일 <span class="required">*</span></label>
                        <input type="date" name="finish_date" required>
                    </div>
                    <div class="col-span-2">
                        <label>공고 URL (선택)</label>
                        <input type="url" name="post_url" placeholder="https://example.com/notice">
                    </div>
                </div>

                <div class="section-header">
                    <span class="section-title">🏠 주택 상세 정보</span>
                </div>
                <p class="section-desc">단지별 주택 정보를 입력하세요. (금액은 '원' 단위로 입력해주세요)</p>
                
                <div id="detailContainer"></div>
                <button type="button" class="btn btn-add" onclick="addDetail()">➕ 주택 상세 추가</button>

                <div class="section-header">
                    <span class="section-title">📌 자격 요건</span>
                </div>
                <p class="section-desc">입주 자격 요건을 입력하세요. (자산/차량가액은 '원' 단위로 입력해주세요)</p>
                
                <div id="criteriaContainer"></div>
                <button type="button" class="btn btn-add-criteria" onclick="addCriteria()">➕ 자격 요건 추가</button>

                <div class="bottom-actions">
                    <button type="button" class="btn btn-cancel" onclick="if(confirm('작성을 취소하시겠습니까?')) location.href='admin_main.php'">취소</button>
                    <button type="submit" class="btn btn-submit">✅ 공고 등록하기</button>
                </div>
            </form>
        </div>
    </div>

    <template id="tmpl-detail">
        <div class="detail-group">
            <div class="detail-group-header">
                <span class="detail-label">🏘️ 주택 상세 #<span class="detail-num">P_INDEX</span></span>
                <button type="button" class="btn btn-del" onclick="removeDetail(this)">🗑️ 삭제</button>
            </div>
            
            <div class="grid-row">
                <div class="col-span-2">
                    <label>단지명 <span class="required">*</span></label>
                    <input type="text" name="details[P_INDEX][complex_name]" placeholder="예) 세종 한솔 A단지" required>
                </div>
                <div>
                    <label>주택형 (평형/면적) <span class="required">*</span></label>
                    <input type="text" name="details[P_INDEX][house_type_name]" placeholder="예) 059.8700X, 36㎡" required>
                </div>
                <div>
                    <label>전용면적 (㎡)</label>
                    <input type="number" step="0.01" name="details[P_INDEX][exclusive_area]" placeholder="59.87">
                </div>
            </div>
            
            <div class="grid-row">
                <div>
                    <label>보증금 (원)</label>
                    <input type="number" name="details[P_INDEX][deposit]" placeholder="40833000">
                </div>
                <div>
                    <label>월세 (원)</label>
                    <input type="number" name="details[P_INDEX][monthly_rent]" placeholder="460680">
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
            <div class="criteria-group-header">
                <span class="criteria-label">
                    <span>✅</span>
                    <span>자격 요건 #<span class="criteria-num">C_INDEX</span></span>
                </span>
                <button type="button" class="btn btn-del" onclick="removeCriteria(this)">🗑️ 삭제</button>
            </div>
            
            <div class="criteria-content">
                <div class="criteria-row">
                    <div>
                        <label>조건명 <span class="required">*</span></label>
                        <input type="text" name="criteria[C_INDEX][criteria_name]" placeholder="예) 무주택 세대주" required>
                    </div>
                    <div>
                        <label>세종시 거주자</label>
                        <div class="checkbox-wrapper">
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_sejong_resident]" value="1"> 필수</label>
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_sejong_resident]" value="0" checked> 무관</label>
                        </div>
                    </div>
                    <div>
                        <label>결혼 여부</label>
                        <div class="checkbox-wrapper">
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_married]" value="1"> 필수</label>
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_married]" value="0" checked> 무관</label>
                        </div>
                    </div>
                    <div>
                        <label>집 소유 여부</label>
                        <div class="checkbox-wrapper">
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_home]" value="1"> 유주택</label>
                            <label class="check-box"><input type="radio" name="criteria[C_INDEX][is_home]" value="0" checked> 무주택</label>
                        </div>
                    </div>
                </div>
                
                <div class="criteria-row-bottom">
                    <div>
                        <label>연령 최소</label>
                        <input type="number" name="criteria[C_INDEX][age_min]" placeholder="19">
                    </div>
                    <div>
                        <label>연령 최대</label>
                        <input type="number" name="criteria[C_INDEX][age_max]" placeholder="39">
                    </div>
                    <div>
                        <label>자산한도 (원)</label>
                        <input type="number" name="criteria[C_INDEX][asset_limit]" placeholder="341000000">
                    </div>
                    <div>
                        <label>차량가액 (원)</label>
                        <input type="number" name="criteria[C_INDEX][car_limit]" placeholder="37080000">
                    </div>
                </div>
            </div>
        </div>
    </template>

    <script>
        const regionsData = <?php echo $regions_json; ?>;
        let detailIndex = 0;
        let criteriaIndex = 0;

        function addDetail() {
            const tmpl = document.getElementById('tmpl-detail').innerHTML;
            const html = tmpl.replace(/P_INDEX/g, detailIndex);
            const div = document.createElement('div');
            div.innerHTML = html;
            const newBlock = div.firstElementChild;
            document.getElementById('detailContainer').appendChild(newBlock);
            
            const regionSelect = newBlock.querySelector('.region-select');
            regionsData.forEach(region => {
                const option = document.createElement('option');
                option.value = region.REGION_ID;
                option.textContent = region.DONG_NAME;
                regionSelect.appendChild(option);
            });
            
            detailIndex++;
        }

        function addCriteria() {
            const tmpl = document.getElementById('tmpl-criteria').innerHTML;
            const html = tmpl.replace(/C_INDEX/g, criteriaIndex);
            const div = document.createElement('div');
            div.innerHTML = html;
            document.getElementById('criteriaContainer').appendChild(div.firstElementChild);
            criteriaIndex++;
        }

        function removeDetail(btn) {
            if(confirm('이 주택 상세를 삭제하시겠습니까?')) {
                btn.closest('.detail-group').remove();
            }
        }

        function removeCriteria(btn) {
            if(confirm('이 자격 요건을 삭제하시겠습니까?')) {
                btn.closest('.criteria-group').remove();
            }
        }

        window.onload = function() {
            addDetail();
            addCriteria();
        };
    </script>

</body>
</html>

<?php if ($conn) @oci_close($conn); ?>