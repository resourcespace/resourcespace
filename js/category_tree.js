
var TreeParents		= new Object();
var TreeNames		= new Object();
var TreeExpand		= new Object();
var TreeID			= new Object();
var TreeNode        = new Object();
var TreeClickable	= new Object();
var TreeChecked		= new Object();
var TreeDynamic		= new Object();
var TreeDrawn		= false;
var TreeTickedDesc	= new Object();
var nocategoriesmessage = '';

function DrawFromNode( field, node, inner, search )
{
	var result			= "";
    var width			= 10;
    var numChildren		= 0;
    
    if( node == -1 ) {
    	width = 0;
    }

	if( !inner ) {
		result += "<table border=0 cellpadding=0 cellspacing=1><td width=" + width + ">&nbsp;</td><td class='treetext' nowrap id='" + field + "_node" + node + "'>";
	}
	
	//loop through all nodes with <node> as the parent
	for( var i = 0, il=TreeParents[field].length; i < il; i++ ) {
        if( TreeParents[field][i] == node ) {

			if( HasTickedDescendants( field, i ) ) { 
            	if( !TreeDrawn ) {
            		TreeExpand[field][i] 	= true; 
            	}
		}

			
            if( TreeExpand[field][i] ) {
				icon = "<img xalign=middle width=11 height=11 hspace=3 src=" + baseurl_short + "gfx/interface/node_ex.gif";
			} else {
				icon = "<img xalign=middle hspace=3 width=11 height=11 src=" + baseurl_short + "gfx/interface/node_unex.gif";
			}
    
    		numChildren = CountChildren( field, i );
    		
            if( numChildren == 0 ) {
            	icon = "<img xalign=top src=" + baseurl_short + "gfx/interface/sp.gif width=11 height=11 hspace=3>";
            } else {
            	icon += " onClick=\"ToggleNode('" + field + "'," + i + "," + search + ");\">";
            }
            
        	result	+= icon;
            checked	= "";
            
            if( TreeChecked[field][i] == 1 ) {
            	checked = "checked";
            }
            
            if( TreeClickable[field][i] ) {
            	result += "<input name='' type=checkbox id=checkbox" + i + " " + checked + " onclick=\"CheckNode('" + field + "'," + i + "," + true + "," + search + ");\">";
            }
            
            result += DePath( TreeNames[field][i] );
            result += "<br>";
            
            if( TreeExpand[field][i] && numChildren > 0 ) {
            	result += DrawFromNode( field, i, false, search );
            }
		}
	}
	
	if( !inner ) {
		result += "</td></tr></table>";
	}
	
	if( node == -1 ) {
    	TreeDrawn = true;
    }

	return( result );
}


function CheckNode(field, node, isuseraction, search)
{
	if ((branch_limit == 1 || branch_limit_field[field] == 1) && search != true) {
		var n = TreeChecked[field][node];
		DeselectAll(field);
	} else {
		var n = node;
	}

	if (TreeChecked[field][node] != 1) {
		TreeChecked[field][node] = 1;
		if (search != true) {
			//Make sure all parents are ticked unless in search mode
			var p = TreeParents[field][n];
			if (p > -1) {
				TreeTickedDesc[field][p] = true;
				if (TreeChecked[field][p] != 1) {
					CheckNode(field, p, false, search);
					unode = TreeParents[field][p];
					if (unode == -1) {
						DrawTree(field, search);
					} else {
						UpdateNode(field, unode, search);
					}
				}
			}	
		}

		if ((branch_limit==1 || branch_limit_field[field] == 1) && search != true) {
			DrawTree(field, search);
		}

	} else {
		TreeChecked[field][node] = 0;
		ResetChildren(field, node, search);
		UpdateNode(field, node, search);
	}

	UpdateStatusBox(field, search);
	UpdateHiddenField(field, isuseraction);
}
	

function UpdateStatusBox( field, search )
{
	var nodes = "";
	
	for( var i = 0, il = typeof(TreeParents[field]) === 'undefined' ? 0 : TreeParents[field].length; i < il; i++ ) {
		if( TreeChecked[field][i] == 1 ) {
			var c = CountTickedChildren( field, i );
			if( c == 0 ) {
				nodes += DescribeNode( field, i, search ) + "<br/>";
			}	
		}	
	}
	
	if( nodes == "" ) {
		document.getElementById( field + "_statusbox" ).innerHTML = nocategoriesmessage;
	} else {
		document.getElementById( field + "_statusbox" ).innerHTML = nodes;
	}
}
	

function StatusReset( field )
{
	//Set the status box to empty
	document.getElementById( field + "_statusbox" ).innerHTML="";
}


function DescribeNode( field, node, search )
{
	// Returns a string containing the node's full 'path'.
	// In search mode only the node itself is returned.
	var path	= DePath( TreeNames[field][node] );
	if(search!=true) {
		var p		= TreeParents[field][node];
		
		while( p > -1 ) {
			path = DePath( TreeNames[field][p] ) + " / " + path;
			var  p = TreeParents[field][p];
		}
	}
	return( path );
}
	

function ResetChildren( field, node, search )
{
	if( search!=true) {
		for( var p = 0; p < TreeParents[field].length; p++ ) {
			if( TreeParents[field][p] == node ) {
				TreeChecked[field][p] = 0;
				TreeTickedDesc[field][p] = false;
				ResetChildren( field, p, search );
				UpdateNode( field, p, search );
			}
		}
	}
}


function ToggleNode( field, node, search )
{ 
	TreeExpand[field][node] = !TreeExpand[field][node];
	UpdateNode( field, TreeParents[field][node], search );
}


function UpdateNode( field, node, search )
{
    if( document.getElementById( field + "_node" + node ) ) {
        document.getElementById( field + "_node" + node ).innerHTML = DrawFromNode( field, node, true, search );
    }
}

    
function CountChildren( field, node )
{
	var count	=	0;
	
	for( var i = 0, il = TreeParents[field].length; i < il; i++ ) {
		if( TreeParents[field][i] == node ) {
			count++;
		}	
	}
	
	return( count );
}


function CountTickedChildren( field, node )
{
	var count	= 0;
	
	for( var i = 0, il = TreeParents[field].length; i < il; i++ ) {
		if( ( TreeParents[field][i] == node ) && ( TreeChecked[field][i] == 1 ) ) {
			count++;
		}	
	}
	
	return( count );
}


function HasTickedDescendants(field, node)
{
	var hasTickedDescendants = false;

	if (typeof TreeTickedDesc[field][node] != 'undefined') {
		return TreeTickedDesc[field][node];
	}

	for (var i = 0, il = TreeParents[field].length; i < il; i++) {
		if ((TreeParents[field][i] == node ) && (TreeChecked[field][i] == 1)) {
			hasTickedDescendants = true;
			break;	
		} else if (TreeParents[field][i] == node) {
			hasTickedDescendants = HasTickedDescendants( field, i );
			if (hasTickedDescendants) { 
				break;
			}
		}
	}

	TreeTickedDesc[field][node] = hasTickedDescendants;
	return(hasTickedDescendants);
}


function CountCheckedRootLevels( field )
{
	var count	= 0;
	
	for( var i = 0, il = TreeParents[field].length; i < il; i++ ) {
		if( ( TreeParents[field][i] == -1 ) && ( TreeChecked[field][i] == 1 ) ) {
		count++;
		}	
	}
	
	return( count );
}


function DeselectAll( field, search )
{
	TreeChecked[field] = new Array();
	DrawTree( field, search );
	UpdateStatusBox( field, search );
	UpdateHiddenField( field , true);
}


function DrawTree( field, search )
{
	document.getElementById( field + "_tree" ).innerHTML = DrawFromNode( field, -1, false, search );
}

function AddNode(field, nodeparent, nodeid, nodename, nodeclickable, nodechecked, nodeexpand)
{
    //try to find an empty space first

    var found = false;

    if (found == false) {
    	c = TreeParents[field].length;
    }
    
	TreeParents[field][c]		= nodeparent;    
    TreeID[field][c]			= nodeid;
    TreeNode[field][nodeid]		= c;
	TreeNames[field][c]			= nodename;
	TreeExpand[field][c]		= false;
    TreeClickable[field][c]		= nodeclickable;
    TreeChecked[field][c]		= nodechecked;
    TreeExpand[field][c]		= false;
    TreeTickedDesc[field][c]	= false;
}

function ResolveParents( field )
{
	for( var c = 0, cl = TreeParents[field].length; c < cl; c++ ) {
    	//resolve nodeparent to internal node id
	    found  = false;
	    
	    if (typeof TreeNode[field][TreeParents[field][c]] != 'undefined') {
			p = TreeNode[field][TreeParents[field][c]]
			found = true;
		}
		
	    if( found ) {
	    	TreeParents[field][c] = p;
			if (TreeChecked[field][c] == 1) {
				TreeTickedDesc[field][p] = true;
			}
	    }
	    
	    if( TreeParents[field][c] == -1 ) {
	    	TreeParents[field][c] = -1;
	    }
	}
}


function UpdateHiddenField( field , user_action)
{
	var f             = '';
    var checked_nodes = [];

	for( var p = 0, pl = TreeID[field].length; p < pl; p++ )
        {
        if(TreeChecked[field][p] == 1)
            {
            f += "|" + TreeNames[field][p];

            checked_nodes[checked_nodes.length] = TreeID[field][p];
            }	
        }

	document.getElementById( field + "_category" ).value = f;

    // Remove all previous nodes as we will add the correct ones next
    var hidden_inputs = document.getElementsByName(field + '[]');
    while(hidden_inputs[0])
        {
        hidden_inputs[0].parentNode.removeChild(hidden_inputs[0]);
        }

    // Send directly the node ID to be searched
    for(var i = 0; i < checked_nodes.length; i++)
        {
        document.getElementById( field + "_tree" ).insertAdjacentHTML('beforeBegin', '<input type="hidden" name="' + field + '[]" value="' + checked_nodes[i] + '">');
        }

	// Update the result counter, if the function is available (e.g. on Advanced Search).
	if( typeof( UpdateResultCount ) == 'function' ) {
		UpdateResultCount();
	}
	
	// Update auto save status
	if (user_action)
		{
		var AutoSaveField=field.replace('field_','');
		if (document.getElementById('AutoSaveStatus'+AutoSaveField))
			{
			AutoSave(AutoSaveField);
			}
		}	
}
	
function DePath( path )
{
	// Returns the last element in a tilda-separated path as produced by StaticSync for the various tree levels.
	pathsplit=path.split( '~' )
	return( pathsplit[pathsplit.length-1] );
}
