<?php
session_start();

include ("../inc/helper.php");
include ("../inc/konfigurasi.php");
include ("../inc/db.pdo.class.php");


$db=new dB($dbsetting);
header('Content-Type: application/json');
if($_POST){
	switch($_POST['act']){
		case 'lihat':
			$jenis=$_POST['j'];
			$id=$_POST['who'];
			$prodi=$_POST['idprodi'];

			if(ctype_digit($id) && ctype_alnum($jenis)){
				if($jenis=="M"){
					$qu="SELECT * FROM tbmhs WHERE idMhs='$id' AND idProdi='$prodi' LIMIT 1";
					$db->runQuery($qu);
					if($db->dbRows()>0){
						$r=$db->dbFetch();
						$response=array();
						$response["profil"] = array();

						$detail['nama_lengkap']=$r['nmLengkap'];
						$detail['id_user']=$r['idmhs'];
						$detail['id_prodi']=$r['idProdi'];
						$detail['username']=$r['nim'];
						$detail['email']=$r['email'];
						$detail['angkatan']=$r['thnmasuk'];
						$detail['foto']=LINK_GAMBAR.$r['foto'];
							
						$response["success"] = "1";
						$response["msg"] = "Data Loaded";
						array_push($response["profil"], $detail);
						echo json_encode($response);
					}else{
						$response["success"] = "0";
						$response["profil"] = null;
						$response["msg"] = "Load Data Failed (Data Not Found)";
						echo json_encode($response);
					}
				}else if($jenis=="D" OR $jenis=="K"){
					$qu="SELECT * FROM tbdosen WHERE idDosen='$id' AND idProdi='$prodi' LIMIT 1";
					$db->runQuery($qu);
					if($db->dbRows()>0){
						$r=$db->dbFetch();
						$response=array();
						$response["profil"] = array();
								
						$detail['nama_lengkap']=$r['nmLengkap'];
						$detail['id_user']=$r['iddosen'];
						$detail['id_prodi']=$r['idProdi'];
						$detail['username']=$r['nip'];
						$detail['email']=$r['email'];
						$detail['nohp']=$r['nohp'];
						$detail['jabatan']=$r['jenis'];
						$detail['foto']=LINK_GAMBAR.$r['foto'];
							
						$response["success"] = "1";
						$response["msg"] = "Data Loaded";
						array_push($response["profil"], $detail);
						echo json_encode($response);
					}else{
						$response["success"] = "0";
						$response["profil"] = null;
						$response["msg"] = "Load Data Failed (Data Not Found)";
						echo json_encode($response);
					}
				}else{
					$response["success"] = "0";
					$response["profil"] = null;
					$response["msg"] = "Load Data Failed";
					echo json_encode($response);
				}
			}else{
				$response["success"] = "0";
				$response["profil"] = null;
				$response["msg"] = "Load Data Failed";
				echo json_encode($response);
			}
		break;

		case 'update':
			$id=$_POST['id'];
			$jenis=$_POST['j'];
			
			if(ctype_digit($id)){

				$RandomNumber 	= rand(0, 9999999999); 

				if($jenis=="M"){
					$ImageName 		= "mhs"; 
				}else{
					$ImageName 		= "dosen"; 
				}
				$NewImageName = $ImageName.'_'.$RandomNumber.'.jpg';

				if($_POST['pic']!=""){
					base64_to_jpeg($_POST['pic'],DIR_GAMBAR.$NewImageName);
					$foto=" foto='".$NewImageName."', ";
				}else{
					$foto="";
				}
				

				if($jenis=="M"){
					$oldpic="SELECT foto FROM tbmhs WHERE idmhs='$id'";
					$db->runQuery($oldpic);
					$rpic=$db->dbFetch();
					$gambarlama=$rpic['foto'];

					$u="UPDATE tbmhs SET 
					nmLengkap='".$_POST['nama']."', 
					$foto
					email='".$_POST['email']."' 
					WHERE idmhs='$id'";
				}else if($jenis=="D" OR $jenis=="K"){
					$oldpic="SELECT foto FROM tbdosen WHERE iddosen='$id'";
					$db->runQuery($oldpic);
					$rpic=$db->dbFetch();
					$gambarlama=$rpic['foto'];

					$u="UPDATE tbdosen SET 
					nmLengkap='".$_POST['nama']."', 
					email='".$_POST['email']."', 
					nohp='".$_POST['nohp']."', 
					$foto
					jabatan='".$_POST['jabatan']."' 
					WHERE iddosen='$id'";
				}

				if($db->runQuery($u)){ 
					$response["success"] = "1";
					$response["profil"] = null;
					$response["msg"] = "Profil Berhasil Diupdate";
					if($_POST['pic']!=""){
						@unlink(DIR_GAMBAR.$gambarlama);
					}
					echo json_encode($response);
				}else{
					@unlink(DIR_GAMBAR.$NewImageName);
					$response["success"] = "0";
					$response["profil"] = null;
					$response["msg"] = "Gagal Update Data - ";
					echo json_encode($response);
				}
			}else{
				$response["success"] = "0";
				$response["profil"] = null;
				$response["msg"] = "Sorry, Cant Process Your Request";
				echo json_encode($response);
			}
		break;

		case 'update_pwd':
			$id=$_POST['id'];
			$jenis=$_POST['j'];
			$pwdbaru=$_POST['pwdbaru'];
			$pwdlama=$_POST['pwdlama'];
			$u="";
			if(ctype_digit($id)){
				if($jenis=="M"){
					$s="SELECT password FROM tbmhs WHERE idmhs='$id' LIMIT 1";
					$db->runQuery($s);
					if($db->dbRows()>0){
						$r=$db->dbFetch();
						if($r['password']==md5($pwdlama)){
							$u="UPDATE tbmhs SET password='".md5($pwdbaru)."' WHERE idmhs='$id'";
						}else{
							echo json_encode(array("success"=>"0",
								"profil"=>null,
								"msg"=>"Password Lama Tidak Sesuai"));
						}
					}else{
						echo json_encode(array("success"=>"0","profil"=>null,"msg"=>"Data Not Found"));
					}
				}else if($jenis=="D" OR $jenis=="K"){
					$s="SELECT password FROM tbdosen WHERE iddosen='$id' LIMIT 1";
					$db->runQuery($s);
					if($db->dbRows()>0){
						$r=$db->dbFetch();
						if($r['password']==md5($pwdlama)){
							$u="UPDATE tbdosen SET password='".md5($pwdbaru)."' WHERE iddosen='$id'";
						}else{
							echo json_encode(array("success"=>"0","profil"=>null,"msg"=>"Password Lama Tidak Sesuai"));
						}
					}else{
						echo json_encode(array("success"=>"0","profil"=>null,"msg"=>"Data Not Found"));
					}
				}
				
				if($db->runQuery($u)){
					echo json_encode(array("success"=>"1","profil"=>null,"msg"=>"Ganti Password Berhasil"));
				}else{
					echo json_encode(array("success"=>"0","profil"=>null,"msg"=>"DBError"));				}
			}else{
				$response["success"] = "0";
				$response["profil"] = null;
				$response["msg"] = "Sorry, Cant Process Your Request";
				echo json_encode($response);
			}
		break;
	}
}else{
	$response["success"] = "0";
	$response["profil"] = null;
	$response["msg"] = "Request not found";
	echo json_encode($response);
}

function base64_to_jpeg($base64_string, $output_file) {
    $ifp = fopen($output_file, "wb"); 

    //$data = explode(',', $base64_string);

    fwrite($ifp, base64_decode($base64_string)); 
    fclose($ifp); 

    return $output_file; 
}