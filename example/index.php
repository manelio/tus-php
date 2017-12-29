<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <title>Resumable File Upload in PHP using Tus Protocol | Demo</title>

    <link href="https://fonts.googleapis.com/css?family=Lato:300,400" rel="stylesheet">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

    <style>
        body {
            letter-spacing: 0.5px;
            line-height: 1.5em;
            font-family: Lato, Helvetica Neue, Helvetica, Arial, sans-serif;
        }

        .container {
            margin: 10px auto;
            font-weight: 300;
            font-size: 1.1em;
        }

        h1 {
            font-size: 2em;
            line-height: 1.3em;
        }

        h3 {
            font-size: 1.5em;
        }

        .gutter-bottom {
            margin-bottom: 15px;
        }

        ol, ul {
            margin-top: 5px;
            padding-bottom: 2.5rem;
        }

        ol li, ul li {
            margin: 1rem 0;
            padding-left: 5px;
        }

        .completed-uploads .panel-body {
            font-weight: bold;
            text-align: center;
            font-size: 1em;
        }

        .progress {
            height: 30px;
            display: none;
        }

        .progress span {
            font-weight: bold;
            display: inline-block;
            margin-top: 5px;
            padding: 0 5px;
        }

        .file-input {
            position: relative;
            overflow: hidden;
            margin: 0;
            color: #333;
            background-color: #fff;
            border-color: #ccc;
        }

        .file-input input[type=file] {
            position: absolute;
            top: 0;
            right: 0;
            margin: 0;
            padding: 0;
            font-size: 20px;
            cursor: pointer;
            opacity: 0;
            filter: alpha(opacity=0);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row">

        <div class="col-md-12">
            <h1>Resumable File Upload in PHP using Tus Protocol</h1><br/>
            <h3 class="gutter-bottom">Instructions</h3>

            <ol>
                <li>Select a large file.</li>
                <li>Watch it upload for a bit, then close this tab before it finishes.</li>
                <li>Come back to the tab, select the same file again, the upload should resume where it left off.</li>
            </ol>
            <div class="panel-body">
                <div class="alert alert-danger" id="error" style="display:none">
                    Oops! Something went wrong. Please refresh the page and try again.
                    This might be because of network/server issue or some external interrupt.
                    Don't worry upload should start where you left off!
                </div>
                <div class="input-group">
                    <input type="text" class="form-control" id="selected-file" disabled="disabled"/>
                    <div class="input-group-btn">
                        <div class="btn btn-default file-input">
                            <span id="browse">Browse</span>

                            <input type="file" name="tus_file" id="tus-file"/>
                        </div>

                        <button type="button" class="btn btn-labeled btn-primary" id="upload" disabled>Upload</button>
                    </div>
                </div>

                <br/>

                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-success active" role="progressbar"
                         aria-valuenow="0" aria-valuemin="0"
                         aria-valuemax="100" style="width: 0%;"><span>0%</span></div>
                </div>

                <hr/>
                <h3 class="gutter-bottom">Uploads</h3>

                <div class="completed-uploads">
                    <p class="info">Successful uploads will be listed here. Try one!</p>
                </div>
            </div>
        </div>

    </div>
</div>

<script type="text/javascript">
  jQuery(document).ready(function ($) {
    var uploadButton = $('#upload'),
      tusFile = $('#tus-file'),
      selectedFile = $('#selected-file');

    $('.file-input').on('change', function (e) {
      var name = e.target.value.split('\\').reverse()[0];

      if (name) {
        selectedFile.val(name);
        uploadButton.attr('disabled', false);
      } else {
        selectedFile.val('');
        uploadButton.attr('disabled', true);
      }
    });

    uploadButton.on('click', function (e) {
      var formData = new FormData,
        fileMeta = tusFile[0].files[0],
        fileSize = fileMeta.size,
        bytesUploaded = 0;

      formData.append('tus_file', fileMeta);

      tusFile.attr('disabled', true);
      uploadButton.attr('disabled', true).text('Calculating...');

      initiateUpload(formData, fileMeta, function () {
        upload(formData, fileSize, function (data) {
          bytesUploaded = data;

          renderProgressBar(bytesUploaded, fileSize);
        }, function (checksum) {
          cleanUp();

          listUploadedFiles(fileMeta, checksum);
        });
      });
    });
  });

  function initiateUpload(formData, fileMeta, cb) {
    $.ajax({
      type: 'POST',
      url: '/verify.php',
      data: formData,
      dataType: 'json',
      processData: false,
      contentType: false,
      success: function (response) {
        if ('error' === response.status) {
          $('#error').html(response.error).fadeIn(200);

          cleanUp();

          return;
        }

        renderProgressBar(response.bytes_uploaded, fileMeta.size);

        if ('uploaded' === response.status) {
          cleanUp();

          listUploadedFiles(fileMeta, response.checksum)
        } else if ('error' !== response.status) {
          cb();
        }
      },
      error: function (error) {
        $('#error').fadeIn(200);

        $('#upload').attr('disabled', false).text('Upload');
        $('#tus-file').attr('disabled', true);
      }
    });
  }

  function upload(formData, fileSize, cb, onComplete) {
    $('#upload').text('Uploading...');

    $.ajax({
      type: 'POST',
      url: '/upload.php',
      data: formData,
      dataType: 'json',
      processData: false,
      contentType: false,
      success: function (response) {
        if ('error' === response.status) {
          $('#error').html(response.error).fadeIn(200);

          cleanUp();

          return;
        }

        var bytesUploaded = response.bytes_uploaded;

        cb(bytesUploaded);

        if (bytesUploaded < fileSize) {
          upload(formData, fileSize, cb, onComplete);
        } else {
          onComplete(response.checksum);
        }
      },
      error: function (error) {
        $('#error').fadeIn(200);
      }
    });
  }

  var cleanUp = function () {
    $('#selected-file').val('');

    $('.progress').hide(100, function () {
      $('.progress-bar')
        .attr('style', 'width: 0%')
        .attr('aria-valuenow', '0');
    });

    $('#upload').attr('disabled', false).text('Upload');
    $('#tus-file').attr('disabled', false);
  };

  var listUploadedFiles = function (fileMeta, checksum) {
    var completedUploads = $('div.completed-uploads');

    completedUploads.find('p.info').remove();
    completedUploads.append(
      '<div class="panel panel-default"><div class="panel-body"><a href="<?= (string) (getenv('SERVER_URL') ?? '') ?>/files/'
      + checksum + '">' + fileMeta.name + '</a> (' + fileMeta.size + ' bytes)</div></div>'
    );
  };

  var renderProgressBar = function (bytesUploaded, fileSize) {
    var percent = (bytesUploaded / fileSize * 100).toFixed(2);

    $('.progress-bar')
      .attr('style', 'width: ' + percent + '%')
      .attr('aria-valuenow', percent)
      .find('span')
      .html(percent + '%');

    $('.progress').show();

    console.info('Uploaded: ' + percent + '%');
  }
</script>
</body>
</html>
