<?php
include "../config/db.php";

$barcode = $_GET['barcode'];

$stmt = $conn->prepare("SELECT id FROM products WHERE barcode=?");
$stmt->bind_param("s",$barcode);
$stmt->execute();

$result = $stmt->get_result();

if($row = $result->fetch_assoc()){
    
    echo json_encode([
        "status"=>"success",
        "product_id"=>$row['id']
    ]);

}else{

    echo json_encode([
        "status"=>"error"
    ]);

}
?>