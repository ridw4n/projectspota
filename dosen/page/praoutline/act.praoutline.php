<?php
session_start();
if($_SESSION['login-dosen']){
if($_POST){
	include ("../../../inc/helper.php");
	include ("../../../inc/gcm_helper.php");
	include ("../../../inc/konfigurasi.php");
	include ("../../../inc/db.pdo.class.php");

	$db=new dB($dbsetting);
	$db2=new dB($dbsetting);

	switch($_POST['act']){

		case 'post_review':
			$idpraoutline=$_POST['idpra'];
			$nip=$_SESSION['login-dosen']['nip'];
			$prodi=$_SESSION['login-dosen']['prodi'];
			$putusan=$_POST['putusan'];
			if($putusan!=""){
				$kep="jenis_review='1', putusan='".$putusan."',";
				$check="SELECT id FROM tbreview WHERE idProdi='$prodi' AND idpraoutline='$idpraoutline'
				AND reviewer='$nip' AND (putusan IS NOT NULL AND putusan <> '')";
				$db->runQuery($check);
				if($db->dbRows()>0){
					echo json_encode(array("result"=>false,"msg"=>"Maaf, Anda Telah Memberikan Keputusan pada Draft Praoutline ini"));
					exit;
				}
			}else{
				$kep="jenis_review='0', putusan='',";
			}
			if(ctype_digit($idpraoutline)){
				if($_POST['text_review']==""){
					echo json_encode(array("result"=>false,"msg"=>"Tanggapan harus diisi"));
				}else{
					$insert="INSERT INTO tbreview SET
					idpraoutline='".$idpraoutline."',
					idProdi='".$prodi."',
					reviewer='".$nip."',
					review_text='".$_POST['text_review']."',
					$kep
					tgl='".CURDATE."',
					wkt='".CURTIME."'";

					if($db->runQuery($insert)){
						echo json_encode(array("result"=>true,"msg"=>"Sukses Menambahkan Tanggapan"));
						if(count($_SESSION['selected_user'])>0){
							//print_r($_SESSION['selected_user']);
							$selecteduser="";
							for($xx=0;$xx<count($_SESSION['selected_user']);$xx++){
								$notif="INSERT INTO tmp_notif_r SET 
								idkonten='$idpraoutline', 
								idProdi='".$_SESSION['login-dosen']['prodi']."', 
								user='".$_SESSION['selected_user'][$xx]."', 
								jns_usr='M',
								tgl='".NOW."', 
								msg='".$_SESSION['login-dosen']['nama_lengkap']." Menambahkan Tanggapan baru', 
								`read`='N'";
								//echo $notif;
								$db->runQuery($notif);
								if($xx==0){
									$selecteduser="'".$_SESSION['selected_user'][$xx]."'";
								}else{
									$selecteduser=",'".$_SESSION['selected_user'][$xx]."'";
								}
							}
							//notif gcm
							//-----------------------------------------------------------------------------
							$g="SELECT regid FROM gcm_service WHERE iduser IN($selecteduser) AND jenisuser ='M'";
							$db->runQuery($g);
							if($db->dbRows()>0){
								$registrationid=array();
								while($r=$db->dbFetch()){
									array_push($registrationid, $r['regid']);
								}
								$isipesan=$_SESSION['login-dosen']['nama_lengkap']." Menambahkan Tanggapan baru";
								$pesan=json_encode(array("jenisnotif"=>"P","pesan"=>$isipesan));
								$message = array("spota" => $pesan);  

								sendPushNotificationToGCM($registrationid, $message);
							}
							//--------------------------------------------------------------------------------		
						}
					}else{
						echo json_encode(array("result"=>false,"msg"=>"Gagal Menambahkan Tanggapan, DBError"));
					}
				}
			}
		break;

		case 'cari':
			$key=$_POST['key'];
			$jenis=$_POST['by'];
			if($jenis=='nim'){
				if(ctype_alnum($key)){
					$by=" tp.nim LIKE '%$key%' ";
				}else{
					$newkey=str_replace("'", "\'", $key);
					$by=" tp.nim LIKE '%$newkey%' ";
				}
				
			}else{
				/*$pecah=explode(" ", $key);
				$jpecah=count($pecah);
				if($jpecah==1){
					if(ctype_alnum($key)){
						$by=" tp.judul LIKE '%$key%' ";
					}else{
						$newkey=str_replace("'", "\'", $key);
						$by=" tp.judul LIKE '%$newkey%' ";
					}
					
				}else{
					$by="";
					if(ctype_alnum($key)){
						for($x=0;$x<$jpecah;$x++){
							if($x==0){
								$by.=" tp.judul like '%$pecah[$x]%' ";
							}else{
								$by.=" OR tp.judul like '%$pecah[$x]%' ";
							}
						}
					}else{
						$newpecah=str_replace("'", "\'", $pecah[$x]);
						for($x=0;$x<$jpecah;$x++){
							if($x==0){
								$by.=" tp.judul like '%$newpecah[$x]%' ";
							}else{
								$by.=" OR tp.judul like '%$newpecah[$x]%' ";
							}
						}
					}
						
				}*/
				if(ctype_alnum($key)){
					$by="  MATCH (tp.judul) AGAINST ('".$key."')";
				}else{
					$newkey=str_replace("'", "\'", $key);
					$by="  MATCH (tp.judul) AGAINST ('".$newkey."')";
				}		
			}
			//include "result-cari.php";
			/*$cari="SELECT * FROM tbpraoutline WHERE $by ORDER BY tgl_upload,wkt_upload,nim,judul";*/
			$cari="SELECT 
				tp.id,
				tp.nim,
				tp.deskripsi,
				tm.nmLengkap as nama,
				tp.judul,
				tp.tgl_upload,
				tp.wkt_upload,
				tp.status_usulan,
				COUNT(tr.id) as jlhreview,
				COUNT(if(tr.jenis_review='0',1,null)) as komentar,
				COUNT(if(tr.jenis_review='1',1,null)) as putusan,
				COUNT(if(tr.putusan='1',1,null)) as setuju,
				count(if(tr.putusan='0',1,null)) as tdk_setuju
			FROM tbpraoutline tp 
			LEFT JOIN tbreview tr ON (tp.id=tr.idpraoutline)
			JOIN tbmhs tm ON (tp.nim=tm.nim)
			WHERE $by GROUP BY tp.id";

			//echo $cari;
			$db->runQuery($cari);
			if($db->dbRows()>0){
				?>
				<h3>Hasil Pencarian '<?php echo $key;?>'</h3>
					<hr>
					<?php 
					while($rcari=$db->dbFetch()){
						if($rcari['status_usulan']==0){
							$statusPraoutline=' - <span class="label label-default">Dalam Proses</span>';
						}else if($rcari['status_usulan']==1){
							$statusPraoutline=' - <span class="label label-success">Judul Diterima</span>';
						}else if($rcari['status_usulan']==2){
							$statusPraoutline=' - <span class="label label-danger">Judul Ditolak</span>';
						}else if($rcari['status_usulan']==3){
							$statusPraoutline=' - <span class="label label-danger">Judul Gugur</span>';
						}
					?>
					<div class="row">
						<div class="col-sm-12">	
							<p><h4 style="text-align:left;margin-top:0"><a href="?page=praoutline&menu=review&prid=<?php echo $rcari['id'];?>"><?php echo strtoupper($rcari['judul']);?></a></h4></p>
							<?php echo substr($rcari['deskripsi'],0,200).' ...';?>
							<div class="row">
								<div class="col-sm-8">
									<p>Oleh <?php echo $rcari['nama']." (".$rcari['nim'].")". $statusPraoutline;?> - <a class="btn btn-xs btn-bricky" href="#"><i class="fa fa-trash-o"></i>Download File</a></p>
								</div>
								<div class="col-sm-4 text-right">
									<p>Jumlah Review : <span class="badge badge-info"><?php echo $rcari['jlhreview'];?></span> | Setuju : <span class="badge badge-success"><?php echo $rcari['setuju'];?></span> | Tidak Setuju : <span class="badge badge-danger"><?php echo $rcari['tdk_setuju'];?></span></p>
								</div><hr/>
							</div>
							<?php switch($rcari['status_usulan']){
								case '1': 
								$kep_final="SELECT *,
								(SELECT nmLengkap FROM tbdosen WHERE tbdosen.nip=pemb1) as dpemb1,
								(SELECT nmLengkap FROM tbdosen WHERE tbdosen.nip=pemb2) as dpemb2,
								(SELECT nmLengkap FROM tbdosen WHERE tbdosen.nip=peng1) as dpeng1,
								(SELECT nmLengkap FROM tbdosen WHERE tbdosen.nip=peng2) as dpeng2
					 			FROM tbrekaphasil WHERE idProdi='".$_SESSION['login-dosen']['prodi']."' AND idpraoutline='".$rcari['id']."' LIMIT 1";
								$db2->runQuery($kep_final);
								if($db2->dbRows()>0){
									$kep=$db2->dbFetch();
									?>
									<div class="alert alert-block alert-info">
										<!-- <h4 class="alert-heading"><i class="fa fa-info-circle"></i> Info!</h4> -->
										<div class="row">
											<div class="col-sm-3">
												<strong><u>Ditetapkan</u></strong> <br/>
												Tanggal : <?php echo tanggalIndo($kep['tgl_kep'],'j F Y');?> <br/>
												Waktu : <?php echo substr($kep['wkt_kep'],0,5);?> <br/>
												Semester : <?php echo $kep['semester'];?> <br/>
												Tahun Akademik : <?php echo $kep['tahun_ajaran'];?>
											</div>
											<div class="col-sm-4">
												<strong><u>Dosen Pembimbing & Penguji</u></strong><br/>
												Pembimbing 1 : <?php echo $kep['dpemb1'];?> <br/>
												Pembimbing 2 : <?php echo $kep['dpemb2'];?> <br/>
												Penguji 1 : <?php echo $kep['dpeng1'];?> <br/>
												Penguji 2 : <?php echo $kep['dpeng2'];?>
											</div>
											<div class="col-sm-4">
												<strong><u>Judul Outline</u></strong><br/>
												<?php echo $kep['judul_final']; ?>
												<strong><u>Catatan</u></strong><br/>
												<?php echo $kep['ket']; ?>
											</div>
										</div>
									</div>
									<?php
								}else{
									echo '<div class="alert alert-danger">
											<i class="clip-cancel-circle"></i>
											<strong>Maaf!</strong> Data Tidak Ditemukan..
										</div>';
								}

								break;

								case '2':
								break;
							} 
							?>
						</div>
					</div>
					<?php
					}
					?>
				<?php
			}else{
				echo '<div class="alert alert-danger">
						<i class="clip-cancel-circle"></i>
						<strong>Maaf!</strong> Data Tidak Ditemukan..
					</div>';
			}
		break;

		case 'open_judul':
			$idpraoutline=$_POST['idpr'];
			$q1="DELETE FROM tbrekaphasil WHERE idpraoutline='".$idpraoutline."'";
			$q2="UPDATE tbpraoutline SET status_usulan='0' WHERE id='".$idpraoutline."' ";
			if($db->runQuery($q1)){
				echo json_encode(array("result"=>true,"msg"=>"Review Draft Praoutline Telah Dibuka Kembali."));
				$db->runQuery($q2);
			}else{
				echo json_encode(array("result"=>false,"msg"=>"Aksi Gagal."));
			}
		break;

		case 'close_judul':
			/*
			-insert data ke rekaphasil
			-insert data ke notif_r
			-update data ke tbpraoutline
			hapus semua data notif_r konten yg sudah terbaca
			*/
			$idpraoutline=$_POST['idpr'];
			$nim=$_POST['nim'];
			$putusan=$_POST['putusan'];
			$keterangan=$_POST['ket'];
			switch ($putusan) {
				case '1':
					$q1="INSERT INTO tbrekaphasil SET 
					idpraoutline='".$idpraoutline."',
					idProdi='".$_SESSION['login-dosen']['prodi']."',
					nim='".$nim."',
					kep_akhir='".$putusan."',
					judul_final='".$_POST['judul_final']."',
					pemb1='".$_POST['pemb1']."',
					pemb2='".$_POST['pemb2']."',
					peng1='".$_POST['peng1']."',
					peng2='".$_POST['peng2']."',
					tgl_kep='".CURDATE."',
					wkt_kep='".CURTIME."',
					semester=(SELECT `values` FROM web_setting WHERE idProdi='".$_SESSION['login-dosen']['prodi']."' AND `name`='smt'),
					tahun_ajaran=(SELECT `values` FROM web_setting WHERE idProdi='".$_SESSION['login-dosen']['prodi']."' AND `name`='thn_ajaran'),
					ket='".$_POST['ket']."'";

					$notif="INSERT INTO tmp_notif_r SET 
								idkonten='$idpraoutline', 
								idProdi='".$_SESSION['login-dosen']['prodi']."', 
								user='".$nim."', 
								jns_usr='M',
								tgl='".NOW."', 
								msg='Usulan Draft Anda Diterima.', 
								`read`='N'";
					$isipesan="Selamat, Draft Praoutline Yang Anda Ajukan Disetujui";
				break;

				case '2':
					$q1="INSERT INTO tbrekaphasil SET 
					idpraoutline='".$idpraoutline."',
					idProdi='".$_SESSION['login-dosen']['prodi']."',
					nim='".$nim."',
					kep_akhir='".$putusan."',
					tgl_kep='".CURDATE."',
					wkt_kep='".CURTIME."',
					semester=(SELECT `values` FROM web_setting WHERE idProdi='".$_SESSION['login-dosen']['prodi']."' AND `name`='smt'),
					tahun_ajaran=(SELECT `values` FROM web_setting WHERE idProdi='".$_SESSION['login-dosen']['prodi']."' AND `name`='thn_ajaran'),
					ket='".$_POST['ket']."'";

					$notif="INSERT INTO tmp_notif_r SET 
								idkonten='$idpraoutline', 
								idProdi='".$_SESSION['login-dosen']['prodi']."', 
								user='".$nim."', 
								jns_usr='M',
								tgl='".NOW."', 
								msg='Usulan Draft Anda Ditolak.', 
								`read`='N'";
					$isipesan="Maaf, Draft Praoutline Yang Anda Ajukan Tidak Disetujui";
				break;

				case '3':
					$q1="INSERT INTO tbrekaphasil SET 
					idpraoutline='".$idpraoutline."',
					idProdi='".$_SESSION['login-dosen']['prodi']."',
					nim='".$nim."',
					kep_akhir='".$putusan."',
					tgl_kep='".CURDATE."',
					wkt_kep='".CURTIME."',
					semester=(SELECT `values` FROM web_setting WHERE idProdi='".$_SESSION['login-dosen']['prodi']."' AND `name`='smt'),
					tahun_ajaran=(SELECT `values` FROM web_setting WHERE idProdi='".$_SESSION['login-dosen']['prodi']."' AND `name`='thn_ajaran'),
					ket='".$_POST['ket']."'";

					$notif="INSERT INTO tmp_notif_r SET 
								idkonten='$idpraoutline', 
								idProdi='".$_SESSION['login-dosen']['prodi']."', 
								user='".$nim."', 
								jns_usr='M',
								tgl='".NOW."', 
								msg='Usulan Draft Anda Gugur.', 
								`read`='N'";
					$isipesan="Maaf, Draft Praoutline Yang Anda Ajukan Gugur";
				break;
			}

			$q2="UPDATE tbpraoutline SET status_usulan='".$putusan."' WHERE id='".$idpraoutline."' ";
			/*if($_POST['pemb1']!="" AND $_POST['pemb2']!="" AND $_POST['peng1']!="" AND $_POST['peng2']!="" ){*/
				if($db->runQuery($q1)){
					echo json_encode(array("result"=>true,"msg"=>"Putusan Draft Praoutline Sukses"));
					$db->runQuery($q2);
					$db->runQuery($notif);
					//gcm
					//-----------------------------------------------------------------------------
					$g="SELECT regid FROM gcm_service WHERE jenisuser IN('M') AND iduser='$nim'";
					$db->runQuery($g);
					$registrationid=array();
					while($r=$db->dbFetch()){
						array_push($registrationid, $r['regid']);
					}
					$pesan=json_encode(array("jenisnotif"=>"P","pesan"=>$isipesan));
					$message = array("spota" => $pesan);  

					sendPushNotificationToGCM($registrationid, $message);
					//--------------------------------------------------------------------------------
				}else{
					echo json_encode(array("result"=>false,"msg"=>"Aksi Gagal."));
				}
			/*}else{
				echo json_encode(array("result"=>false,"msg"=>"Aksi Gagal. Silakan Tentukan Dosen Pembimbing dan Penguji"));
				exit();
			}*/
		break;

		case 'getmhs':
			$db3=new dB($dbsetting);
			$nip=$_POST['nip'];
			$jenis=$_POST['jenis'];

			$d="SELECT nmLengkap FROM tbdosen WHERE nip='$nip'";
			$db3->runQuery($d);
			if($db3->dbRows()>0){
				$dosen=$db3->dbFetch();
			}

			switch($jenis){
				case 'pemb1':
					$qq="SELECT tm.nim, tm.nmLengkap, trh.judul_final, trh.semester
					FROM tbrekaphasil trh
			        LEFT JOIN tbdosen td ON (trh.pemb1=td.nip)
			        LEFT JOiN tbmhs tm ON (trh.nim=tm.nim)
					WHERE  td.nip='$nip' ORDER BY trh.semester, tm.nim";
					$txt="Sebagai Pembimbing 1";
				break;
				
				case 'pemb2':
					$qq="SELECT tm.nim, tm.nmLengkap, trh.judul_final, trh.semester
					FROM tbrekaphasil trh
			        LEFT JOIN tbdosen td ON (trh.pemb2=td.nip)
			        LEFT JOiN tbmhs tm ON (trh.nim=tm.nim)
					WHERE  td.nip='$nip' ORDER BY trh.semester, tm.nim";
					$txt="Sebagai Pembimbing 2";
				break;
				
				case 'peng1':
					$qq="SELECT tm.nim, tm.nmLengkap, trh.judul_final, trh.semester
					FROM tbrekaphasil trh
			        LEFT JOIN tbdosen td ON (trh.peng1=td.nip)
			        LEFT JOiN tbmhs tm ON (trh.nim=tm.nim)
					WHERE  td.nip='$nip' ORDER BY trh.semester, tm.nim";
					$txt="Sebagai Penguji 1";
				break;
				
				case 'peng2':
					$qq="SELECT tm.nim, tm.nmLengkap, trh.judul_final, trh.semester
					FROM tbrekaphasil trh
			        LEFT JOIN tbdosen td ON (trh.peng2=td.nip)
			        LEFT JOiN tbmhs tm ON (trh.nim=tm.nim)
					WHERE td.nip='$nip' ORDER BY trh.semester, tm.nim";
					$txt="Sebagai Penguji 2";
				break;
				}
				
			$db3->runQuery($qq);
			echo $dosen['nmLengkap']." : ";
			echo $txt."<br/>";
			echo '<table class="daftamahasiswa table table-striped table-bordered table-hover">';
			echo '<thead><tr>
				<th style="text-align: center">NIM</th>
				<th style="text-align: center">Nama Mahasiswa</th>
				<th style="text-align: center">Semeseter</th>
				<th style="text-align: center">Judul</th>
			</tr></thead> ';
			if($db3->dbRows()>0){
				echo '<tbody>';
				while($m=$db3->dbFetch()){
					echo '<tr>
						<td>'.$m['nim'].'</td>
						<td>'.$m['nmLengkap'].'</td>
						<td>'.$m['semester'].'</td>
						<td>'.$m['judul_final'].'</td>
					</tr> ';
				}
				echo "</tbody>";
			}else{
				echo '<tbody>';
				echo '<tr>
						<td></td>
						<td></td>
						<td></td>
						<td></td>
					</tr> ';
				echo '</tbody>';
			}
			echo '</table>';
			
		break;
	}
}
}
?>