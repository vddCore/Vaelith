(function () {
  'use strict';

  var ghAlerts = function (converter) {
    return [
      {
        type: 'output',
        filter: function (text, converter, options) {
          return text.replace(
            /<blockquote>\s*<p>\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*<br\s*\/?>\s*([\s\S]*?)<\/p>\s*<\/blockquote>/g,
            function (match, alertType, content) {
              let alertClass = alertType.toLowerCase();
              return `<div class="markdown-alert markdown-alert-${alertClass}">\n
                <p class="markdown-alert-title">${alertType}</p>
                <p>${content.trim()}</p>\n
              </div>`;
            }
          );
        }
      }
    ];
  };

  window.showdown.extension('ghAlerts', ghAlerts);
}());