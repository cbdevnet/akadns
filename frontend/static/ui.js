/**
	GUI glue code. Pretty hacky, whatever.
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

function updateText(elemid,text){
	document.getElementById(elemid).innerHTML=text;
}

function createElementWithText(tag, text){
	var elem=document.createElement(tag);
	elem.innerHTML=text;
	return elem;
}

function createElementWithChild(tag, child){
	var elem=document.createElement(tag);
	elem.appendChild(child);
	return elem;
}

function createAliasDisplay(aliasname){
	var aliasElem=createElementWithText("span",aliasname);
	aliasElem.setAttribute("class","alias");

	var closeButton=document.createElement("img");
	closeButton.setAttribute("src","static/del.png");
	closeButton.setAttribute("class","del-img");
	closeButton.onclick=function(){deleteAlias(this.parentNode);};
	
	aliasElem.appendChild(closeButton);
	return aliasElem;
}

function createNetworkTableEntry(ip,data){
	var firstCol=createElementWithChild("td",createElementWithText("div",ip));
	firstCol.appendChild(createElementWithText("em",data["hostname"]));
	firstCol.style.width="5em";
	
	var secondCol=createElementWithChild("td",createElementWithText("strong","aka"));
	secondCol.style.width="4em";
	secondCol.style.paddingLeft="1em";
	
	var thirdCol=document.createElement("td");
	if(data["aliases"]){
		for(var i=0;i<data["aliases"].length;i++){
			var aliasElem=createElementWithText("a",data["aliases"][i]);
			aliasElem.href="http://"+data["aliases"][i]+"/";
			aliasElem.setAttribute("class","alias");
			thirdCol.appendChild(aliasElem);
		}
	}
	else{
		thirdCol.appendChild(createElementWithText("em","[none]"));
	}
	
	var tableRow=document.createElement("tr");
	tableRow.appendChild(firstCol);
	tableRow.appendChild(secondCol);
	tableRow.appendChild(thirdCol);
	
	return tableRow;
}
