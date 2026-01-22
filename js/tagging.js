// Comment tagging
// Detect the first couple of characters during tagging and pass it to the TaggingLookup function, which will provide
// some suggestions to the user.

var TaggingLastResult=""; // Store the last suggested result set and only update the UI when the response changes.


function TaggingProcess(object)
    {
    // Look for the @ sign
    var atsign=object.value.lastIndexOf('@');
    var lastspace=object.value.lastIndexOf(' ');
    if (atsign>-1 && lastspace<atsign) // Must be in the midst of typing a tag, i.e. no space.
        {
        var taggingname=object.value.substring(atsign+1);
        if (taggingname.length>=2) {TaggingLookup(object,taggingname);} else {TaggingStop(object);}
        }
    else    
        {
        // No longer tagging, cancel any tagging hints
        TaggingStop(object);
        }
    }

// Search for taggingname in the users
function TaggingLookup(object,taggingname)
    {
    // Call API to get a list of matching users and call TaggingSuggest on each returned matching user record to suggest them.    
    api('get_users',{"find":taggingname},function(response)
        {
        var s = JSON.stringify(response);
        if (s!=TaggingLastResult) // The results have changed. Update!
            {
            TaggingStop(object);
            for (var n=0;n<response.length;n++)
                {
                TaggingSuggest(object,response[n]["username"]);
                }
            TaggingLastResult=s;
            }
        },
        jQuery(object).data('api-native-csrf')
    );
    }

// Add a suggestion hint.
function TaggingSuggest(object,username)
    {
    var TagHint = document.createElement("a");
    TagHint.innerHTML=username;
    TagHint.className='TaggingHint';
    TagHint.href='#';
    TagHint.onclick=function(evt)
        {
        // Auto complete with this username.
        var atsign=object.value.lastIndexOf('@');
        object.value=object.value.substring(0,atsign+1) + this.innerHTML.split(' ').join('_') + " ";
        object.focus();
        TaggingStop(object);
        return false;
        }
    object.parentNode.insertBefore(TagHint,object.nextSibling);
    }

function TaggingStop(object)
    {
    // Clear all tag hints
    var array = Array.prototype.slice.call(object.parentNode.childNodes);
    array.forEach(TaggingRemoveHint);
    TaggingLastResult="";
    }

function TaggingRemoveHint(item, index)
    {
    if (item.className=='TaggingHint')
        {
        item.remove();
        }
    }