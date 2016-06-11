function peerforum_collapse(e, obj) {
    api: M.cfg.wwwroot + '/mod/peerforum/collapse.php',

    e.preventDefault();

    Y.log('Enetered method');
    var postid = obj.postid;

    var ioconfig = {
        method: 'POST',
        data: {'sesskey' : M.cfg.sesskey},
        on: {
            success: function (o, response) {
              var data = Y.JSON.parse(response.responseText);

              if (data.result) {

                var id = 'peergradefeedbacks' + postid;
                var actionlink = 'actionlink' + postid;

                var div_feedbacks = document.getElementById(id);
                var display = document.getElementById(id).style.display;

                if(display == "none"){
                    document.getElementById(id).style.display = "block";
                    document.getElementById(actionlink).innerHTML = 'Collapse all peergrades';
                }
                if(display == "block"){
                    document.getElementById(id).style.display = "none";
                    document.getElementById(actionlink).innerHTML = 'Expand all peergrades';
                }

              }
            },
            failure: function (o, response) {
              alert('Not OK!');
            }
         }
    };

    Y.io(M.cfg.wwwroot + '/mod/peerforum/collapse.php', ioconfig);
}

function peerforum_collapse_config(e, obj) {
    api: M.cfg.wwwroot + '/mod/peerforum/collapse.php',

    e.preventDefault();

    Y.log('Enetered method');
    var postid = obj.postid;

    var ioconfig = {
        method: 'POST',
        data: {'sesskey' : M.cfg.sesskey},
        on: {
            success: function (o, response) {
              var data = Y.JSON.parse(response.responseText);

              if (data.result) {

                var id = 'peergradeconfig' + postid;
                var actionlink = 'actionlink_config' + postid;

                var div_feedbacks = document.getElementById(id);
                var display = document.getElementById(id).style.display;

                if(display == "none"){
                    document.getElementById(id).style.display = "block";
                    document.getElementById(actionlink).innerHTML = 'Collapse details';
                }
                if(display == "block"){
                    document.getElementById(id).style.display = "none";
                    document.getElementById(actionlink).innerHTML = 'Expand details';
                }

              }
            },
            failure: function (o, response) {
              alert('Not OK!');
            }
         }
    };

    Y.io(M.cfg.wwwroot + '/mod/peerforum/collapse.php', ioconfig);
}
