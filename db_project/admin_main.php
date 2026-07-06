<?php
session_start();

// 1. 관리자 권한 체크
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 2) {
    header("Location: login.php");
    exit();
}

include 'db_conn.php';

$stats = [
    'total_posts' => 0,
    'active_posts' => 0,
    'total_users' => 0,
    'total_applications' => 0,
    'housing_types' => [],
    'recent_posts' => []
];

if ($conn) {
    $db_status_icon = "🟢";
    $db_status_msg = "DB 연결 성공";
    
    // [Query 1] 총 등록 공고 수
    $sql = "SELECT COUNT(*) AS CNT FROM HousingPost";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $row = oci_fetch_array($stmt, OCI_ASSOC);
    $stats['total_posts'] = $row['CNT'];

    // [Query 2] 진행 중인 공고
    $sql = "SELECT COUNT(*) AS CNT FROM HousingPost WHERE SYSDATE BETWEEN START_DATE AND FINISH_DATE";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $row = oci_fetch_array($stmt, OCI_ASSOC);
    $stats['active_posts'] = $row['CNT'];
    
    // [Query 3] 총 회원 수
    $sql = "SELECT COUNT(*) AS CNT FROM User_Table";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $row = oci_fetch_array($stmt, OCI_ASSOC);
    $stats['total_users'] = $row['CNT'];
    
    // [Query 4] 총 신청 현황
    $sql = "SELECT COUNT(*) AS CNT FROM User_House";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    $row = oci_fetch_array($stmt, OCI_ASSOC);
    $stats['total_applications'] = $row['CNT'];
    
    // [Query 5] 주택 유형별 현황
    $sql = "SELECT 
                ht.type_name,
                COUNT(hp.post_id) AS total_posts,
                SUM(CASE WHEN SYSDATE BETWEEN hp.START_DATE AND hp.FINISH_DATE THEN 1 ELSE 0 END) AS active_posts
            FROM HousingType ht
            LEFT JOIN HousingPost hp ON ht.type_id = hp.type_id
            GROUP BY ht.type_name
            ORDER BY ht.type_name";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    while ($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) {
        $stats['housing_types'][] = $row;
    }
    
    // [Query 6] 최근 등록 공고 5개
    $sql = "SELECT post_id, post_title, TO_CHAR(POST_DATE, 'YYYY-MM-DD') AS P_DATE, START_DATE, FINISH_DATE
            FROM HousingPost 
            ORDER BY post_id DESC 
            FETCH FIRST 5 ROWS ONLY";
    $stmt = oci_parse($conn, $sql);
    oci_execute($stmt);
    while ($row = oci_fetch_array($stmt, OCI_ASSOC+OCI_RETURN_NULLS)) {
        $stats['recent_posts'][] = $row;
    }
} else {
    $db_status_icon = "🔴";
    $db_status_msg = "DB 연결 실패";
}

$admin_name = isset($_SESSION['username']) ? $_SESSION['username'] : '관리자';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 대시보드 - 세종 청년주택</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* (기존 스타일과 동일하게 유지) */
        :root { --sejong-blue: #256CB6; --sejong-dark: #1a4a80; --sejong-light: #E6F2FF; --bg-color: #F5F7FA; --white: #FFFFFF; --border-color: #DDE2E5; --text-main: #333333; --text-sub: #666666; --danger: #FF4D4F; --success: #28a745; --sidebar-width: 260px; --header-height: 64px; --warning: #FA8C16; }
        * { box-sizing: border-box; }
        body { font-family: 'Noto Sans KR', sans-serif; margin: 0; background-color: var(--bg-color); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: var(--sidebar-width); background-color: var(--white); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; flex-shrink: 0; z-index: 100; }
        .brand { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; font-size: 20px; font-weight: 800; color: var(--sejong-blue); border-bottom: 1px solid var(--border-color); letter-spacing: -0.5px; }
        .brand span { color: var(--sejong-dark); margin-left: 5px; }
        .menu { list-style: none; padding: 20px 0; margin: 0; }
        .menu-link { display: flex; align-items: center; padding: 12px 28px; text-decoration: none; color: var(--text-sub); font-weight: 500; transition: all 0.2s; }
        .menu-link:hover { background-color: var(--sejong-light); color: var(--sejong-blue); }
        .menu-link.active { background-color: var(--sejong-blue); color: var(--white); font-weight: 700; box-shadow: 0 2px 6px rgba(37, 108, 182, 0.3); }
        .main-layout { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .top-header { height: var(--header-height); background-color: var(--white); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; padding: 0 32px; }
        .page-title { font-size: 18px; font-weight: 700; color: var(--text-main); }
        .user-profile { font-size: 14px; display: flex; gap: 10px; align-items: center; color: var(--text-sub); }
        .status-badge { font-size: 13px; background: #eee; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
        .btn-logout { border: 1px solid var(--border-color); background: white; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; color: var(--text-sub); transition: 0.2s; }
        .btn-logout:hover { border-color: var(--sejong-blue); color: var(--sejong-blue); }
        .content-scroll { flex: 1; overflow-y: auto; padding: 32px; }
        
        .dashboard-main-area { display: flex; gap: 24px; max-width: 1600px; margin: 0 auto 32px auto; }
        .stat-stack-left { display: flex; flex-direction: column; gap: 24px; flex-basis: 300px; flex-shrink: 0; }
        .stat-card { background: var(--white); border-radius: 12px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between; height: 140px; transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(37, 108, 182, 0.15); border-color: var(--sejong-blue); }
        .stat-title { font-size: 14px; color: var(--text-sub); font-weight: 600; }
        .stat-value { font-size: 36px; font-weight: 800; color: var(--sejong-blue); margin-top: 10px; }
        .stat-icon { align-self: flex-end; font-size: 24px; opacity: 0.2; margin-top: auto; }
        
        .type-breakdown-panel { flex: 1; background: var(--white); border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 24px; }
        .panel-title { font-size: 18px; font-weight: 700; color: var(--text-main); }
        .type-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .type-table th, .type-table td { padding: 12px 0; font-size: 14px; }
        .type-table th { color: var(--sejong-blue); font-weight: 700; border-bottom: 2px solid #eee; }
        .type-table td { font-weight: 500; border-bottom: 1px dotted #eee; }
        .type-table tfoot td { border-top: 2px solid var(--sejong-blue); font-weight: 700; color: var(--sejong-dark); }
        .active-cell { color: var(--success); font-weight: 600; }
        
        .section-title-wrapper { margin-bottom: 15px; max-width: 1600px; margin: 0 auto 15px auto; }
        .section-title-text { font-size: 16px; font-weight: 700; color: var(--text-sub); border-left: 4px solid var(--sejong-blue); padding-left: 10px; }
        
        .quick-menu { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; max-width: 1600px; margin: 0 auto 40px auto; }
        .btn-quick { padding: 25px; background: var(--white); border: 1px solid var(--border-color); border-radius: 12px; text-align: center; cursor: pointer; text-decoration: none; color: var(--text-main); transition: all 0.2s; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .btn-quick:hover { border-color: var(--sejong-blue); background-color: var(--sejong-light); color: var(--sejong-blue); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 108, 182, 0.15); }
        .btn-icon { font-size: 28px; }
        .btn-text { font-weight: 700; font-size: 16px; }
        
        .panel { background: var(--white); border-radius: 12px; border: 1px solid var(--border-color); padding: 24px; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); max-width: 1600px; margin: 0 auto; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px; }
        .link-more { font-size: 13px; color: var(--sejong-blue); text-decoration: none; font-weight: 500; transition: 0.2s; }
        .link-more:hover { text-decoration: underline; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px 12px; border-bottom: 2px solid var(--border-color); color: var(--text-sub); font-size: 14px; font-weight: 600; background-color: #FAFBFC; }
        td { padding: 16px 12px; border-bottom: 1px solid var(--border-color); font-size: 14px; color: var(--text-main); }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #F4F9FF; }
        
        .status-label { color: var(--success); font-weight: 600; background: #e6ffed; padding: 4px 10px; border-radius: 12px; font-size: 12px; }
        .status-upcoming { color: #C2410C; font-weight: 600; background: #FFEDD5; padding: 4px 10px; border-radius: 12px; font-size: 12px; }
        .status-closed { color: #999; font-weight: 600; background: #eee; padding: 4px 10px; border-radius: 12px; font-size: 12px; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="brand">SEJONG<span>ADMIN</span></div>
        <ul class="menu">
            <li><a href="admin_main.php" class="menu-link active">📊 대시보드</a></li>
            <li><a href="admin_post_create.php" class="menu-link">📝 공고 등록</a></li>
            <li><a href="admin_post_manage.php" class="menu-link">📂 공고 관리</a></li>
            <li><a href="admin_users.php" class="menu-link">👥 권한 관리</a></li>
        </ul>
    </nav>

    <div class="main-layout">
        <header class="top-header">
            <div class="page-title">관리자 대시보드</div>
            <div class="user-profile">
                <span class="status-badge"><?php echo $db_status_icon; ?> <?php echo $db_status_msg; ?></span>
                <span><?php echo htmlspecialchars($admin_name); ?>님</span>
                <button class="btn-logout" onclick="if(confirm('로그아웃 하시겠습니까?')) location.href='logout.php'">로그아웃</button>
            </div>
        </header>

        <div class="content-scroll">
            <div class="dashboard-main-area">
                <div class="stat-stack-left">
                    <div class="stat-card">
                        <div class="stat-title">총 회원 수</div>
                        <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-icon">👥</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-title">총 등록 공고</div>
                        <div class="stat-value"><?php echo number_format($stats['total_posts']); ?></div>
                        <div class="stat-icon">📄</div>
                    </div>
                </div>

                <div class="type-breakdown-panel">
                    <div class="panel-title" style="margin-bottom: 10px;">주택 유형별 공고 현황</div>
                    <table class="type-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 45%;">유형명</th>
                                <th style="text-align: right; width: 25%;">총 공고 수</th>
                                <th style="text-align: right; width: 30%;">진행 중</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_posts_sum = 0;
                            $total_active_sum = 0;
                            if (count($stats['housing_types']) > 0):
                                foreach ($stats['housing_types'] as $type): 
                                    $total_posts_sum += $type['TOTAL_POSTS'];
                                    $total_active_sum += $type['ACTIVE_POSTS'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($type['TYPE_NAME']); ?></td>
                                <td style="text-align: right;"><?php echo number_format($type['TOTAL_POSTS']); ?></td>
                                <td style="text-align: right;" class="active-cell"><?php echo number_format($type['ACTIVE_POSTS']); ?></td>
                            </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 20px; color: #999;">데이터가 없습니다.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td style="font-weight: 700;">총 합계</td>
                                <td style="text-align: right; font-weight: 700;"><?php echo number_format($total_posts_sum); ?></td>
                                <td style="text-align: right; font-weight: 700; color: var(--success);"><?php echo number_format($total_active_sum); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="section-title-wrapper">
                <h3 class="section-title-text">빠른 실행</h3>
            </div>
            <div class="quick-menu">
                <a href="admin_post_create.php" class="btn-quick">
                    <span class="btn-icon">✏️</span>
                    <span class="btn-text">신규 공고 등록</span>
                </a>
                <a href="admin_post_manage.php" class="btn-quick">
                    <span class="btn-icon">📋</span>
                    <span class="btn-text">공고 목록 조회</span>
                </a>
                <a href="admin_users.php" class="btn-quick">
                    <span class="btn-icon">👥</span>
                    <span class="btn-text">회원 관리</span>
                </a>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">최근 등록된 공고 (Recent Posts)</span>
                    <a href="admin_post_manage.php" class="link-more">전체보기 →</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>공고 제목</th>
                            <th style="width: 150px;">등록일(공고일)</th>
                            <th style="width: 100px;">상태</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($stats['recent_posts']) > 0): ?>
                            <?php 
                            foreach ($stats['recent_posts'] as $post): 
                                // ★ 상태 로직 수정: 접수예정 추가
                                $now = new DateTime();
                                $start = new DateTime($post['START_DATE']);
                                $finish = new DateTime($post['FINISH_DATE']);
                                $now->setTime(0,0,0); $start->setTime(0,0,0); $finish->setTime(0,0,0);
                                
                                $status_class = '';
                                $status_text = '';
                                
                                if ($now < $start) {
                                    $status_class = 'status-upcoming';
                                    $status_text = '접수예정';
                                } elseif ($now >= $start && $now <= $finish) {
                                    $status_class = 'status-label';
                                    $status_text = '진행중';
                                } else {
                                    $status_class = 'status-closed';
                                    $status_text = '마감됨';
                                }
                            ?>
                            <tr>
                                <td><?php echo $post['POST_ID']; ?></td>
                                <td><strong><?php echo htmlspecialchars($post['POST_TITLE']); ?></strong></td>
                                <td><?php echo $post['P_DATE']; ?></td>
                                <td><span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 30px; color:#999;">등록된 공고가 없습니다.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</body>
</html>

<?php
if ($conn) oci_close($conn);
?>