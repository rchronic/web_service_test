<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Firebase\JWT\JWT;

// Routes

$app->get('/api', function (Request $request, Response $response, array $args) {
    $this->logger->info("Authenticated!");
    // print_r($request->getAttribute('decoded_token_data'));
    return $response->withJson(["status" => "ok"]);
});

$app->get("/api/top_ten_disease", function (Request $request, Response $response){
    $kd_puskesmas = $request->getAttribute('decoded_token_data')->data->kd_puskesmas;
    // print_r($kd_puskesmas);
    $sql = "select kd_penyakit, penyakit, SUM(total) as jumlah from (select i.kd_penyakit, i.penyakit, COUNT(pp.kd_penyakit) as total from pelayanan_penyakit pp join pelayanan p on pp.kd_pelayanan = p.kd_pelayanan join mst_icd i on pp.kd_penyakit = i.kd_penyakit where p.tgl_pelayanan >= DATE(NOW()) - INTERVAL 1 MONTH AND p.kd_puskesmas = '".$kd_puskesmas."' group by pp.kd_penyakit union all select i.kd_penyakit, i.penyakit, COUNT(pg.kd_penyakit) as total from pasien_gigi pg join mst_icd i ON pg.kd_penyakit = i.kd_penyakit where pg.tanggal >= DATE(NOW()) - INTERVAL 1 MONTH AND pg.kd_puskesmas = '".$kd_puskesmas."' group by pg.kd_penyakit) t group by kd_penyakit, penyakit order by jumlah desc limit 0,10";
    $stmt = $this->db->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll();
    if(!$result) {
      return $response->withJson(["status" => "success", "data" => "empty"], 200);
    }
    return $response->withJson(["status" => "success", "data" => $result], 200);
});

$app->post('/login', function (Request $request, Response $response, array $args) {
    $input = $request->getParsedBody();
    $sql = "SELECT * FROM user WHERE username= :username";
    $sth = $this->db->prepare($sql);
    $sth->bindParam("username", $input['username']);
    $sth->execute();
    $user = $sth->fetchObject();
    if(!$user) {
        return $this->response->withJson(['error' => true, 'message' => 'These credentials do not match our records.']);
    }
    if (!(md5($input['password']) === $user->password)) {
        return $this->response->withJson(['error' => true, 'message' => 'Password do not match our records.']);
    }

    $tokenId = $user->user_id;
    $issuedAt = time();
    $notBefore = $issuedAt;
    $expire = $notBefore + 60 * 60;

    $token = JWT::encode([
      'iat' => $issuedAt,
      'jti' => $tokenId,
      'nbf' => $notBefore,
      'exp' => $expire,
      'data' => [
        'username' => $user->username,
        'kd_puskesmas' => $user->kd_puskesmas
      ]
    ], $this->get('settings')['jwt']['secret'], "HS256");

    return $this->response->withJson(['token' => $token]);
});
