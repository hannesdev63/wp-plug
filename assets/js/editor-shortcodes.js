(function (blocks, element, components, blockEditor, i18n) {
  'use strict';

  var el = element.createElement;
  var __ = i18n.__;
  var ServerSideRender = window.wp && window.wp.serverSideRender ? window.wp.serverSideRender : null;

  function registerMsGraphBlock(config) {
    blocks.registerBlockType(config.name, {
      title: __(config.title, 'wp-ms365-graph'),
      icon: config.icon || 'media-document',
      category: 'widgets',
      description: __(config.description, 'wp-ms365-graph'),
      attributes: config.attributes,

      edit: function (props) {
        var attrs = props.attributes;
        var previewNode;

        if (ServerSideRender) {
          previewNode = el(ServerSideRender, {
            block: config.name,
            attributes: attrs,
          });
        } else {
          previewNode = el('p', null, __('Preview is not available in this editor.', 'wp-ms365-graph'));
        }

        return el(
          'div',
          { className: 'wp-ms365-graph-block-editor' },
          el(
            blockEditor.InspectorControls,
            null,
            el(
              components.PanelBody,
              { title: __('Shortcode Settings', 'wp-ms365-graph'), initialOpen: true },
              config.fields.map(function (field) {
                if (field.type === 'text') {
                  return el(components.TextControl, {
                    key: field.name,
                    label: field.label,
                    help: field.help || '',
                    value: attrs[field.name] || '',
                    onChange: function (value) {
                      props.setAttributes({ [field.name]: value });
                    }
                  });
                }

                if (field.type === 'number') {
                  return el(components.TextControl, {
                    key: field.name,
                    type: 'number',
                    label: field.label,
                    help: field.help || '',
                    value: attrs[field.name] !== undefined ? attrs[field.name] : field.default,
                    onChange: function (value) {
                      props.setAttributes({ [field.name]: value === '' ? field.default : Number(value) });
                    }
                  });
                }

                if (field.type === 'toggle') {
                  return el(components.ToggleControl, {
                    key: field.name,
                    label: field.label,
                    help: field.help || '',
                    checked: !!attrs[field.name],
                    onChange: function (value) {
                      props.setAttributes({ [field.name]: value });
                    }
                  });
                }

                return null;
              })
            )
          ),
          el('h4', null, __('Preview', 'wp-ms365-graph')),
          previewNode,
          el('p', null, __('Shortcode equivalent:', 'wp-ms365-graph')),
          el('code', null, '[' + config.shortcodeTag + config.shortcodeAttributes(attrs) + ']')
        );
      },

      save: function () {
        return null;
      }
    });
  }

  function buildShortcodeAttributes(attrs, fieldNames) {
    var parts = [];
    fieldNames.forEach(function (name) {
      if (attrs[name] === undefined || attrs[name] === null || attrs[name] === '') {
        return;
      }

      if (typeof attrs[name] === 'boolean') {
        parts.push(name + '="' + (attrs[name] ? 'true' : 'false') + '"');
        return;
      }

      parts.push(name + '="' + attrs[name].toString().replace(/"/g, '&quot;') + '"');
    });

    return parts.length ? ' ' + parts.join(' ') : '';
  }

  registerMsGraphBlock({
    name: 'wp-ms365-graph/calendar',
    title: 'Microsoft 365 Calendar',
    description: 'Insert the Graph calendar shortcode.',
    shortcodeTag: 'msgraph_calendar',
    attributes: {
      limit: { type: 'number', default: 10 },
      days: { type: 'number', default: 30 },
      title: { type: 'string', default: '' },
      class: { type: 'string', default: '' },
      show_headers: { type: 'boolean', default: true },
    },
    shortcodeAttributes: function (attrs) {
      return buildShortcodeAttributes(attrs, ['limit', 'days', 'title', 'class', 'show_headers']);
    },
    fields: [
      { type: 'number', name: 'limit', label: __('Limit', 'wp-ms365-graph'), default: 10 },
      { type: 'number', name: 'days', label: __('Days', 'wp-ms365-graph'), default: 30 },
      { type: 'text', name: 'title', label: __('Title', 'wp-ms365-graph') },
      { type: 'text', name: 'class', label: __('CSS Class', 'wp-ms365-graph') },
      { type: 'toggle', name: 'show_headers', label: __('Show Headers', 'wp-ms365-graph') },
    ],
  });

  registerMsGraphBlock({
    name: 'wp-ms365-graph/files',
    title: 'OneDrive Files',
    description: 'Insert a OneDrive file table shortcode.',
    shortcodeTag: 'msgraph_files',
    attributes: {
      folder: { type: 'string', default: '' },
      limit: { type: 'number', default: 50 },
      title: { type: 'string', default: '' },
      columns: { type: 'string', default: '' },
      column_order: { type: 'string', default: '' },
      hide_columns: { type: 'string', default: '' },
      download_columns: { type: 'string', default: 'file' },
      class: { type: 'string', default: '' },
      table_class: { type: 'string', default: '' },
      item_class: { type: 'string', default: '' },
      show_headers: { type: 'boolean', default: true },
    },
    shortcodeAttributes: function (attrs) {
      return buildShortcodeAttributes(attrs, ['folder', 'limit', 'title', 'columns', 'column_order', 'hide_columns', 'download_columns', 'class', 'table_class', 'item_class', 'show_headers']);
    },
    fields: [
      { type: 'text', name: 'folder', label: __('Folder', 'wp-ms365-graph') },
      { type: 'number', name: 'limit', label: __('Limit', 'wp-ms365-graph'), default: 50 },
      { type: 'text', name: 'title', label: __('Title', 'wp-ms365-graph') },
      { type: 'text', name: 'columns', label: __('Columns', 'wp-ms365-graph'), help: __('Comma-separated: file,size,modified', 'wp-ms365-graph') },
      { type: 'text', name: 'column_order', label: __('Column Order', 'wp-ms365-graph') },
      { type: 'text', name: 'hide_columns', label: __('Hide Columns', 'wp-ms365-graph') },
      { type: 'text', name: 'download_columns', label: __('Download Columns', 'wp-ms365-graph') },
      { type: 'text', name: 'class', label: __('Wrapper Class', 'wp-ms365-graph') },
      { type: 'text', name: 'table_class', label: __('Table Class', 'wp-ms365-graph') },
      { type: 'text', name: 'item_class', label: __('Item Class', 'wp-ms365-graph') },
      { type: 'toggle', name: 'show_headers', label: __('Show Headers', 'wp-ms365-graph') },
    ],
  });

  registerMsGraphBlock({
    name: 'wp-ms365-graph/sharepoint-library',
    title: 'SharePoint Library',
    description: 'Insert a SharePoint library table shortcode.',
    shortcodeTag: 'msgraph_sharepoint_library',
    attributes: {
      site_id: { type: 'string', default: '' },
      drive_id: { type: 'string', default: '' },
      folder: { type: 'string', default: '' },
      limit: { type: 'number', default: 50 },
      title: { type: 'string', default: '' },
      columns: { type: 'string', default: '' },
      column_order: { type: 'string', default: '' },
      hide_columns: { type: 'string', default: '' },
      download_columns: { type: 'string', default: 'file' },
      image_columns: { type: 'string', default: '' },
      image_basepath: { type: 'string', default: '' },
      class: { type: 'string', default: '' },
      table_class: { type: 'string', default: '' },
      item_class: { type: 'string', default: '' },
      show_headers: { type: 'boolean', default: true },
    },
    shortcodeAttributes: function (attrs) {
      return buildShortcodeAttributes(attrs, ['site_id', 'drive_id', 'folder', 'limit', 'title', 'columns', 'column_order', 'hide_columns', 'download_columns', 'image_columns', 'image_basepath', 'class', 'table_class', 'item_class', 'show_headers']);
    },
    fields: [
      { type: 'text', name: 'site_id', label: __('Site ID', 'wp-ms365-graph') },
      { type: 'text', name: 'drive_id', label: __('Drive ID', 'wp-ms365-graph') },
      { type: 'text', name: 'folder', label: __('Folder', 'wp-ms365-graph') },
      { type: 'number', name: 'limit', label: __('Limit', 'wp-ms365-graph'), default: 50 },
      { type: 'text', name: 'title', label: __('Title', 'wp-ms365-graph') },
      { type: 'text', name: 'columns', label: __('Columns', 'wp-ms365-graph'), help: __('Comma-separated: file,size,modified', 'wp-ms365-graph') },
      { type: 'text', name: 'column_order', label: __('Column Order', 'wp-ms365-graph') },
      { type: 'text', name: 'hide_columns', label: __('Hide Columns', 'wp-ms365-graph') },
      { type: 'text', name: 'download_columns', label: __('Download Columns', 'wp-ms365-graph') },
      { type: 'text', name: 'image_columns', label: __('Image Columns', 'wp-ms365-graph'), help: __('Comma-separated columns rendered as image file names.', 'wp-ms365-graph') },
      { type: 'text', name: 'image_basepath', label: __('Image Base Path', 'wp-ms365-graph'), help: __('Base URL prepended to image file names (default: WordPress uploads URL).', 'wp-ms365-graph') },
      { type: 'text', name: 'class', label: __('Wrapper Class', 'wp-ms365-graph') },
      { type: 'text', name: 'table_class', label: __('Table Class', 'wp-ms365-graph') },
      { type: 'text', name: 'item_class', label: __('Item Class', 'wp-ms365-graph') },
      { type: 'toggle', name: 'show_headers', label: __('Show Headers', 'wp-ms365-graph') },
    ],
  });

  registerMsGraphBlock({
    name: 'wp-ms365-graph/login-button',
    title: 'Microsoft Sign-In Button',
    description: 'Insert the Graph login-button shortcode.',
    shortcodeTag: 'msgraph_login_button',
    attributes: {
      label: { type: 'string', default: '' },
      redirect_to: { type: 'string', default: '' },
      class: { type: 'string', default: '' },
    },
    shortcodeAttributes: function (attrs) {
      return buildShortcodeAttributes(attrs, ['label', 'redirect_to', 'class']);
    },
    fields: [
      { type: 'text', name: 'label', label: __('Label', 'wp-ms365-graph') },
      { type: 'text', name: 'redirect_to', label: __('Redirect To', 'wp-ms365-graph') },
      { type: 'text', name: 'class', label: __('CSS Class', 'wp-ms365-graph') },
    ],
  });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n);
