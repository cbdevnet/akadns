/**
	AKA DNS Panel
	Frontend for that simple dnsmasq API provider thingy.
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
	Configuration and stuff
*/
var SERVER_URL="../backend/dns.php";
var CURRENT_HASH;

function queryData(){
	ajax.asyncGet(SERVER_URL+"?get", function(req){
			if(req.status==200){
				//parse returned data
				rData=JSON.parse(req.responseText);
				
				//parse status message and code
				updateText("status-message",rData["status"]);
				if(rData["code"]!=1){
					return;
				}
				
				//save config hash
				CURRENT_HASH=rData["confHash"];
				
				//process connection data
				updateText("head-name",rData["conn"]["ip"]);
				updateText("info-hostname",rData["conn"]["name"]);
				updateText("info-mac",rData["conn"]["mac"]);
				
				//kill table
				var netTable=document.getElementById("network-table");
				netTable.innerHTML="";
				
				//delete visible own aliases
				var aliasElems=document.getElementsByClassName("alias");
				while(aliasElems.length>0){
					aliasElems[0].parentNode.removeChild(aliasElems[0]);
				}
				
				var aliasInput=document.getElementById("alias-input");
				
				//add currently active own aliases
				if(rData["data"][rData["conn"]["ip"]]["aliases"]){
					rData["data"][rData["conn"]["ip"]]["aliases"].forEach(function(elem, index, arr){
							aliasInput.parentNode.insertBefore(createAliasDisplay(elem),aliasInput);
						});
				}
				
				//create table
				var activeHosts=Object.keys(rData["data"]);
				for(var i=0;i<activeHosts.length;i++){
					if(activeHosts[i]!=rData["conn"]["ip"]){
						netTable.appendChild(createNetworkTableEntry(activeHosts[i],rData["data"][activeHosts[i]]));
					}
				}
			}
			else{
				updateText("status-message","Failed to query server");
			}
		}, 
	function(e){
			updateText("status-message","Exception (query): "+e.message);
		});
}

function dataPingback(){
	ajax.asyncGet(SERVER_URL+"?hash",function(req){
			//parse returned data
			rData=JSON.parse(req.responseText);
			
			//parse status (silently)
			if(rData["code"]!=1){
				updateText("status-message",rData["status"]);
				return;
			}
			
			//check for updates
			if(rData["status"]!=CURRENT_HASH){
				updateText("status-message","Updating data");
				queryData();
			}
		},
	function(e){
		updateText("status-message","Exception (ping): "+e.message);
		});
}

function init(){
	setInterval(dataPingback,5000);
	queryData();
}

function deleteAlias(elem){
	ajax.asyncPost(SERVER_URL+"?rem","alias="+encodeURI(elem.textContent),function(req){
			if(req.status==200){
				//parse returned data
				rData=JSON.parse(req.responseText);
				
				//parse status message and code
				updateText("status-message",rData["status"]);
				if(rData["code"]!=1){
					return;
				}
				
				//remove node
				elem.parentNode.removeChild(elem);
			}
			else{
				updateText("status-message","Server encountered an error");
			}
		},
	function(e){
			updateText("status-message","Exception (rem): "+e.message);
		});
}

function createAlias(input){
	var reqAlias=input.value;
	input.value="";
	ajax.asyncPost(SERVER_URL+"?add","alias="+encodeURI(reqAlias),function(req){
			if(req.status==200){
				//parse returned data
				rData=JSON.parse(req.responseText);
				
				//parse status message and code
				updateText("status-message",rData["status"]);
				if(rData["code"]!=1){
					return;
				}
				
				//insert into dom
				input.parentNode.insertBefore(createAliasDisplay(reqAlias),input);
			}
			else{
				updateText("status-message","Server encountered an error");
			}
		},
	function(e){
			updateText("status-message","Exception (add): "+e.message);
		});
}
