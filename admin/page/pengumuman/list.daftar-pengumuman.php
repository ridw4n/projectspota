<?php
	session_start();
	$idprodi=$_SESSION['login-admin']['prodi'];
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 * Easy set variables
	 */
	
	/* Array of database columns which should be read and sent back to DataTables. Use a space where
	 * you want to insert a non-database field (for example a counter or static image)
	 */
	$aColumns = array('tp.judul');
	
	/* Indexed column (used for fast and accurate table cardinality) */
	$sIndexColumn = "tp.id";
	
	/* DB table to use */
	$sTable = "tbpengumuman tp";
	
	/* Database connection information */
	include ("../../../inc/helper.php");
	include ("../../../inc/konfigurasi.php");
	include ("../../../inc/db.pdo.class.php");

	$db=new dB($dbsetting);
	
	/* 
	 * Paging
	 */
	$sLimit = "";
	if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
	{
		$sLimit = "LIMIT ".intval( $_GET['iDisplayStart'] ).", ".
			intval( $_GET['iDisplayLength'] );
	}
	
	
	/*
	 * Ordering
	 */
	$sOrder = "ORDER BY tp.tgl DESC, tp.judul ASC";
	
	/* 
	 * Filtering
	 */
	$sWhere = "";
	if ( isset($_GET['sSearch']) && $_GET['sSearch'] != "" )
	{
		$sWhere = "WHERE (";
		for ( $i=0 ; $i<count($aColumns) ; $i++ )
		{
			if ( isset($_GET['bSearchable_'.$i]) && $_GET['bSearchable_'.$i] == "true" )
			{
				$sWhere .= "".$aColumns[$i]." LIKE '%".$_GET['sSearch']."%' OR ";
			}
		}
		$sWhere = substr_replace( $sWhere, "", -3 );
		$sWhere .= ')';
	}
	
	/* Individual column filtering */
	for ( $i=0 ; $i<count($aColumns) ; $i++ )
	{
		if ( isset($_GET['bSearchable_'.$i]) && $_GET['bSearchable_'.$i] == "true" && $_GET['sSearch_'.$i] != '' )
		{
			if ( $sWhere == "" )
			{
				$sWhere = "WHERE ";
			}
			else
			{
				$sWhere .= " AND ";
			}
			$sWhere .= "".$aColumns[$i]." LIKE '%".$_GET['sSearch_'.$i]."%' ";
		}
	}


	$where2="";
	if($sWhere!=''){
		$where2="AND tp.idProdi='$idprodi'";
	}else{
		$where2="WHERE tp.idProdi='$idprodi'";
	}
	/*
	 * SQL queries
	 * Get data to display
	 */
	$sQuery0 = "
		SELECT * FROM $sTable
		$sWhere
		$where2
		$sOrder 
		";
	//echo $sQuery0;
	$db->runQuery($sQuery0);
	$iFilteredTotal = $db->dbRows();

	$result=$db->runQuery($sQuery0.$sLimit);

	/* Total data set length */
	$sQuery2 = "
		SELECT COUNT(tp.id) as total FROM $sTable $where2
	";
	$db->runQuery($sQuery2);
	$aResultTotal = $db->dbFetch();
	$iTotal = $aResultTotal['total'];

	/*$rResultTotal = mysql_query( $sQuery, $gaSql['link'] ) or fatal_error( 'MySQL Error: ' . mysql_errno() );
	$aResultTotal = mysql_fetch_array($rResultTotal);
	$iTotal = $aResultTotal[0];*/
	
	
	/*
	 * Output
	 */

	$output = array(
		"sEcho" => intval($_GET['sEcho']),
		"iTotalRecords" => $iTotal,
		"iTotalDisplayRecords" => $iFilteredTotal,
		"aaData" => array()
	);
	
	while ( $aRow = $db->dbFetch($result) )
	{
		//print_r($aRow);
		$row = array();
		$tujuan="";
		if($aRow['publish']=="N"){
			$publish=" <code> -draft </code>";
			$terbit='<li role="presentation">
								<a role="menuitem" tabindex="-1" href="#" onClick="PublishPengumuman('.$aRow['id'].')">
									<i class="clip-earth-2"></i> Terbitkan
								</a>
							</li>';
			$tombol="btn-warning";
		}else{
			
			if($aRow['slide']=='Y'){
				$publish=' - <span class="label label-success"> Slider </span>';
			}else{
				$publish="";
			}

			$terbit="";
			$tombol="btn-primary";
		}
		switch($aRow['tujuan']){
			case 'A':
				$tujuan="Semua";
			break;
			case 'D':
				$tujuan="Dosen";
			break;
			case 'M':
				$tujuan="Mahasiswa";
			break;
		}

		$row[0]=$aRow['judul'].$publish;
		$row[1]=$tujuan;
		$row[2]=tanggalIndo($aRow['tgl'],'j F Y, H:i');
		$tombolaksi='<div class="btn-group">
						<a class="btn '.$tombol.' dropdown-toggle btn-sm" data-toggle="dropdown" href="#">
							<i class="icon-cog"></i> <span class="caret"></span>
						</a>
						<ul role="menu" class="dropdown-menu pull-right">
							'.$terbit.'
							<li role="presentation">
								<a role="menuitem" tabindex="-1" href="#" onClick="EditPengumuman('.$aRow['id'].')">
									<i class="icon-edit"></i> Edit
								</a>
							</li>							
							<li role="presentation">
								<a role="menuitem" tabindex="-1" href="#" onClick="HapusPengumuman('.$aRow['id'].')">
									<i class="icon-remove"></i> Hapus
								</a>
							</li>
							
						</ul>
					</div>';

		$row[3]=$tombolaksi;

		$output['aaData'][] = $row;
		// print_r($row);
		
	}
	
	echo json_encode( $output );
?>