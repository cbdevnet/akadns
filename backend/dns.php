<?php
	/**
		Simple API provider for reading a dnsmasq-style leases file
		and creating a unix-hosts-file style alias output file.
	*/
	
	/**
		DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE 
		Version 2, December 2004 

		Copyright (C) 2004 Sam Hocevar <sam@hocevar.net> 

			Everyone is permitted to copy and distribute verbatim or modified 
			copies of this license document, and changing it is allowed as long 
			as the name is changed. 

		DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE 
		TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION 

			0. You just DO WHAT THE FUCK YOU WANT TO.
	*/
	
	/**
		The file to read active leases from, typically generated by dnsmasq
		Needs to be readable by this script's execution context
	*/
	//$LEASEFILE="/var/lib/misc/dnsmasq.leases";
	$LEASEFILE="test.leases";
	
	/**
		The output file in normal unix-style hosts syntax, to be read
		by dnsmasq. Must be writable in this scripts execution context.
		You might not want to use /etc/hosts here, because that might
		be written to by other code. Also, you need to add this to your
		dnsmasq config as addn-hosts file and enable polling.
	*/
	//$HOSTSFILE="/etc/hosts";
	$HOSTSFILE="test.hosts";
	
	//initialize often-used vars because im a lazy typer
	$REMOTE=$_SERVER["REMOTE_ADDR"];
	
	//set blank status
	$ret["status"]="No action";
	$ret["code"]=1;
	
	//get lease file data
	$dLease=file_get_contents($LEASEFILE); //we dont need to write this and its pretty static, so using fgc should be safe. i hope.
	if($dLease===FALSE){
		$ret["status"]="Could not open lease input file (".$LEASEFILE.")";
		$ret["code"]=0;
		die(json_encode($ret));
	}
	$hLease=md5($dLease);
	
	//get alias file data
	$fHosts=fopen($HOSTSFILE,"r+");
	if($fHosts===FALSE){
		$ret["status"]="Could not open alias output file (".$HOSTSFILE.")";
		$ret["code"]=0;
		die(json_encode($ret));
	}
	
	if(!flock($fHosts,LOCK_EX)){//exclusive lock, we don't want this file to change while we are working with it
		$ret["status"]="Could not acquire exclusive lock on output file";
		$ret["code"]=0;
		fclose($fHosts);
		die(json_encode($ret));
	}
	
	$dHosts="";
	if(filesize($HOSTSFILE)>0){
		$dHosts=fread($fHosts,filesize($HOSTSFILE));
	}
	$hHosts=md5($dHosts);
	
	if(isset($_GET["hash"])){
		$ret["status"]=$hLease.$hHosts;
		$ret["code"]=1;
	}
	else{
		$ret["confHash"]=$hLease.$hHosts;
		
		//parse leases
		$dLease=preg_split("/[\s,]+/",$dLease,-1,PREG_SPLIT_NO_EMPTY);
		//var_dump($dLease);
		if(count($dLease)%5!=0){
			$ret["status"]="Lease file in unknown format";
			$ret["code"]=0;
			flock($fHosts,LOCK_UN);
			fclose($fHosts);
			die(json_encode($ret));
		}
		for($i=0;$i<(count($dLease)/5);$i++){
			$leases[$dLease[($i*5)+2]]["mac"]=$dLease[($i*5)+1];
			if($dLease[($i*5)+3]=="*"){
				$leases[$dLease[($i*5)+2]]["hostname"]="[none]";
			}
			else{
				$leases[$dLease[($i*5)+2]]["hostname"]=$dLease[($i*5)+3];
			}
		}
		
		//check if connected from an active lease
		if(!isset($leases[$REMOTE])){
			$ret["status"]="Not connecting from a leased address";
			$ret["code"]=0;
			flock($fHosts,LOCK_UN);
			fclose($fHosts);
			die(json_encode($ret));
		}
		
		//set status data
		$ret["conn"]["ip"]=$REMOTE;
		$ret["conn"]["mac"]=$leases[$REMOTE]["mac"];
		$ret["conn"]["name"]=$leases[$REMOTE]["hostname"];
		
		//parse active alias data
		$dHosts=preg_split("/(\r\n|\n|\r)/",$dHosts);
		for($i=0;$i<count($dHosts);$i++){
			$dHosts[$i]=explode(" ",$dHosts[$i]);
			
			//TODO add checking for ip syntax here
			if(isset($leases[($dHosts[$i][0])])){
				for($j=2;$j<count($dHosts[$i]);$j++){
					$leases[($dHosts[$i][0])]["aliases"][$j-2]=$dHosts[$i][$j];
				}
			}
		}
		
		
		$BUFDIRTY=false;
		
		//add an alias
		if(isset($_GET["add"])&&isset($_POST["alias"])){
			//check if valid
			if(preg_match("/^[a-zA-Z0-9][a-zA-Z0-9\-\_]+[a-zA-Z0-9]$/",$_POST["alias"])){
				//valid name, check for existence
				$avail=true;
				
				$kLeases=array_keys($leases);
				
				for($i=0;$i<count($kLeases);$i++){
					if($leases[$kLeases[$i]]["hostname"]==$_POST["alias"]){
						$avail=false;
						$ret["status"]="Alias is already a hostname";
						$ret["code"]=0;
						break;
					}
					if($avail&&isset($leases[$kLeases[$i]]["aliases"])){
						foreach($leases[$kLeases[$i]]["aliases"] as $cAlias){
							if($cAlias==$_POST["alias"]){
								$avail=false;
								$ret["status"]="Alias is already taken";
								$ret["code"]=0;
								break;
							}
						}
					}
				}
				if($avail){
					//add to array
					if(!isset($leases[$REMOTE]["aliases"])){
						$leases[$REMOTE]["aliases"][0]=$_POST["alias"];
					}
					else{
						$leases[$REMOTE]["aliases"][count($leases[$REMOTE]["aliases"])]=$_POST["alias"];
					}
					$BUFDIRTY=true;
				}
			}
			else{
				$ret["status"]="Alias contains invalid character";
				$ret["code"]=0;
			}
		}
		
		//remove an alias
		if(isset($_GET["rem"])&&isset($_POST["alias"])){
			//check if existing
			//TODO use foreach
			for($i=0;$i<count($leases[$REMOTE]["aliases"]);$i++){
				if($leases[$REMOTE]["aliases"][$i]==$_POST["alias"]){
					//remove
					unset($leases[$REMOTE]["aliases"][$i]);
					$BUFDIRTY=true;
				}
			}
			if(!$BUFDIRTY){
				$ret["status"]="Could not find alias to delete";
				$ret["code"]=0;
			}
		}
		
		if($BUFDIRTY){
			//write
			rewind($fHosts);
			ftruncate($fHosts,0);
			foreach($leases as $kHost => $vHost){
				if(isset($vHost["aliases"])&&count($vHost["aliases"])>0){
					//fwrite($fHosts,$kHost." ".$vHost["hostname"]); //FIXME this just uses the normal hostname as reverse.
					
					//HACKFIX for unnamed (no-dhcp-hostname) hosts
					if($vHost["hostname"]=="[none]"){
						fwrite($fHosts,$kHost." ".$vHost["aliases"][0]);//use first alias
					}
					else{
						fwrite($fHosts,$kHost." ".$vHost["hostname"]);//still use hostname
					}
					
					foreach($vHost["aliases"] as $cAlias){
						fwrite($fHosts," ".$cAlias);
					}
					fwrite($fHosts,PHP_EOL);
				}
			}
			//set status
			$ret["status"]="Changes written.";
			$ret["code"]=1;
		}
		
		if(isset($_GET["get"])){
			//dump
			$ret["data"]=$leases;
			$ret["status"]="Data query OK";
			$ret["code"]=1;
		}
	}	
	
	//unlock, close, print status
	flock($fHosts,LOCK_UN);
	fclose($fHosts);
	die(json_encode($ret));
?>