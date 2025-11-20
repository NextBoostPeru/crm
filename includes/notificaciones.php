<?php
function enviarNotificacionPush($titulo, $mensaje) {
    $data = [
        "app_id" => "TU_APP_ID_DE_ONESIGNAL",
        "included_segments" => ["All"],
        "headings" => ["es" => $titulo],
        "contents" => ["es" => $mensaje],
    ];

    $ch = curl_init("https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic d653973d-a687-46ef-8b7f-d7d875c07e33'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}
