function peerforum_assignpeersparent(e, obj) {
    api: M.cfg.wwwroot + '/mod/peerforum/assignpeersparent.php',

    e.preventDefault();

    var itemid = obj.itemid;
    var courseid = obj.courseid;
    var postauthor = obj.postauthor;

    var ioconfig = {
        method: 'POST',
        data: {'itemid' : itemid, 'courseid' : courseid, 'postauthor' : postauthor},
        on: {
            success: function (o, response) {
              var data = Y.JSON.parse(response.responseText);

              if (data.result) {

                  var id = 'nonepeers' + itemid;
                  var actionlink = 'actionlinkpeers' + itemid;
                  var peersassigned = 'peersassigned' + itemid;
                  var menuassignpeer = 'menuassignpeer' + itemid;
                  var menuremovepeer = 'menuremovepeer' + itemid;
                  var div_none_peergraders = document.getElementById(id);

                  document.getElementById(id).style.display = "none";
                  document.getElementById(peersassigned).innerHTML = data.peersnames;
                  document.getElementById(menuassignpeer).innerHTML = data.canassign;
                  document.getElementById(menuremovepeer).innerHTML = data.canremove;
              }
            },
            failure: function (o, response) {
              alert('Error on peerforum_assignpeersparent');
            }
         }
    };
    Y.io(M.cfg.wwwroot + '/mod/peerforum/assignpeersparent.php', ioconfig);
}
