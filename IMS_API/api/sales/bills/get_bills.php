<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Pagination parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $records_per_page;

    // Date filters
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

    // Count total records
    $count_query = "SELECT COUNT(*) as total FROM billing_header bh";
    if ($start_date && $end_date) {
        $count_query .= " WHERE DATE(date) >= :start_date AND DATE(date) <= :end_date";
    }
    
    $count_stmt = $db->prepare($count_query);
    if ($start_date && $end_date) {
        $count_stmt->bindParam(":start_date", $start_date);
        $count_stmt->bindParam(":end_date", $end_date);
    }
    $count_stmt->execute();
    $total_rows = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_rows / $records_per_page);

    // Get paginated data
    $query = "SELECT 
                bh.id,
                bh.bill_no,
                bh.username,
                bh.full_name,
                bh.bill_type,
                DATE(bh.date) as date,
                (SELECT SUM(price * qty) FROM billing_details WHERE bill_id = bh.id) as total_amount 
            FROM billing_header bh";

    if ($start_date && $end_date) {
        $query .= " WHERE DATE(date) >= :start_date AND DATE(date) <= :end_date";
    }
    $query .= " ORDER BY id DESC LIMIT :offset, :limit";

    $stmt = $db->prepare($query);
    
    if ($start_date && $end_date) {
        $stmt->bindParam(":start_date", $start_date);
        $stmt->bindParam(":end_date", $end_date);
    }
    $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
    $stmt->bindParam(":limit", $records_per_page, PDO::PARAM_INT);
    
    $stmt->execute();
    $bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $status_code = 200;
    http_response_code($status_code);
    echo json_encode([
        "status" => $status_code,
        "success" => true,
        "data" => [
            "records" => $bills,
            "pagination" => [
                "page" => $page,
                "total_pages" => $total_pages,
                "records_per_page" => $records_per_page,
                "total_records" => $total_rows
            ]
        ]
    ]);

} catch (Exception $e) {
    $status_code = 503;
    http_response_code($status_code);
    echo json_encode([
        "status" => $status_code,
        "success" => false,
        "message" => "Unable to fetch bills.",
        "error" => $e->getMessage()
    ]);
}