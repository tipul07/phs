<?php
/** @var \phs\system\core\views\PHS_View $this */

use phs\PHS;
use phs\PHS_Tenants;
use phs\libraries\PHS_Plugin;

/** @var \phs\plugins\admin\PHS_Plugin_Admin $admin_plugin */
/** @var \phs\system\core\libraries\PHS_Ui_translations $ui_translations */
if (!($admin_plugin = $this->view_var('admin_plugin'))
    || !($ui_translations = $this->view_var('ui_translations'))
) {
    return $this->_pt('Error loading required resources.');
}

$translation_files = $this->view_var('translation_files') ?: [];
$excluding_paths = $this->view_var('excluding_paths') ?: [];

$available_languages_arr = [];
foreach ($this::get_defined_languages() ?: [] as $lang_id => $lang_arr) {
    $available_languages_arr[] = [
        'id'    => $lang_id,
        'label' => $lang_arr['title'] ?? $lang_id,
    ];
}

echo $this->sub_view('ractive/bootstrap');
?>
<form id="ui_translations_form" name="ui_translations_form" method="post"
      action="<?php echo PHS::url(['a' => 'ui_translations', 'ad' => 'translations', 'c' => 'api', 'p' => 'admin']); ?>">
    <input type="hidden" name="foobar" value="1" />

    <div class="form_container">
        <div id="PHS_RActive_Ui_translations_target"></div>
    </div>
</form>

<script id="PHS_RActive_Ui_translations_template" type="text/html">

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Language Files')?></h3>
    </section>

<table class="table table-hover" style="width=100%;">
    <thead>
    <tr>
        <th class="text-center">&nbsp;</th>
        <th class="text-center"><?php echo $this->_pt( 'File' )?></th>
        <th class="text-center"><?php echo $this->_pt( 'Modified' )?></th>
        <th class="text-center"><?php echo $this->_pt( 'Size' )?></th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td class="font-weight-bold">
            <?php echo $this->_pt( 'POT File' )?>
            {{#if pot_filesize > 0 }}
            <a href="javascript:void(0)"
               on-click="@this.po_file_info(pot_filename)"><i class="fa fa-info action-icons"></i></a>
            {{/if}}
        </td>
        <td><a href="javascript:void(0)"
               on-click="@this.trigger_download(pot_filename, pot_filesize)">{{pot_filename}}</a>
        </td>
        <td class="text-right">{{@this.format_date_timestamp(pot_modified, "d-m-Y H:i")}}</td>
        <td class="text-right"><span title="<?php echo $this::_e(sprintf('%s bytes', '{{pot_filesize}}'))?>">{{@this.format_file_size(pot_filesize)}}</span></td>
    </tr>
    <tr>
        <td class="font-weight-bold"><?php echo $this->_pt( 'POT Files List')?></td>
        <td><a href="javascript:void(0)"
               on-click="@this.trigger_download(pot_list_filename, pot_list_filesize)">{{pot_list_filename}}</a></td>
        <td class="text-right">{{@this.format_date_timestamp(pot_list_modified, "d-m-Y H:i")}}</td>
        <td class="text-right"><span title="<?php echo $this::_e(sprintf('%s bytes', '{{pot_list_filesize}}'))?>">{{@this.format_file_size(pot_list_filesize)}}</span></td>
    </tr>
    {{#each language_files}}
    <tr>
        <td class="font-weight-bold">
            <?php echo $this->_pt('Language')?>: {{.lang}}
            {{#if .size > 0 }}
            <a href="javascript:void(0)"
               on-click="@this.po_file_info(.file)"><i class="fa fa-info action-icons"></i></a>
            {{/if}}
        </td>
        <td>
            <a href="javascript:void(0)"
               on-click="@this.trigger_download(.file, .size)">{{.file}}</a>
        </td>
        <td class="text-right">{{@this.format_date_timestamp(.modified, "d-m-Y H:i")}}</td>
        <td class="text-right"><span title="<?php echo $this::_e(sprintf('%s bytes', '{{.size}}'))?>">{{@this.format_file_size(.size)}}</span></td>
    </tr>
    {{/each}}
    </tbody>
</table>

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Regenerate POT File')?></h3>
    </section>

{{#if excluding_paths.length === 0 }}
<p class="p-5 text-center"><?php echo $this->_pt('You can add directories which will be excluded when generating POT file...'); ?></p>
{{ else }}
<table class="table table-hover">
    <thead>
    <tr>
        <th style="width: 1%;">#</th>
        <th class="text-center"><?php echo $this->_pt( 'Excluded directory' )?></th>
        <th>&nbsp;</th>
    </tr>
    </thead>
    <tbody>
    {{#each excluding_paths}}
    <tr>
        <td>{{@index+1}}</td>
        <td>
            <input type="text" name="excluding_paths[]" value="{{excluding_paths[@index]}}" class="form-control" />
        </td>
        <td class="text-center">
            <a href="javascript:void(0)" onclick="this.blur()"
               on-click="@this.delete_excluding_path(@index)"><i class="fa fa-times-circle-o action-icons"
                                                                 title="<?php echo $this->_pte('Delete excluding path')?>"></i></a>
        </td>
    </tr>
    {{/each}}
    </tbody>
</table>
{{/if}}

<div class="form-group">
    <a href="javascript:void(0)" class="btn btn-primary"
       on-click="@this.add_directory_exception()">
        <i class="fa fa-plus"></i> <?php echo $this->_pt('Add directory exception')?></a>
</div>

<div class="form-group">
    <input type="button" id="do_generate_pot_file" name="do_generate_pot_file"
           class="btn btn-primary submit-protection ignore_hidden_required"
           on-click="@this.generate_pot_file()"
           value="<?php echo $this->_pte('Generate POT file'); ?>" />
</div>

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Regenerate PO File')?></h3>
    </section>

    <div class="form-group row">
        <label for="language_to_update" class="col-sm-2 col-form-label"><?php echo $this->_pt('For language'); ?></label>
        <div class="col-sm-10">
            <select name="language_to_update" id="language_to_update" value="{{language_to_update}}">
                <option value=""> - <?php echo $this->_pt('Select language'); ?> - </option>
                {{#each @this.get_languages_arr() }}
                <option value="{{.id}}">{{.label}}</option>
                {{/each}}
            </select>
        </div>
    </div>

<div class="form-group">
    <input type="button" id="do_generate_po_file" name="do_generate_po_file"
           class="btn btn-primary submit-protection ignore_hidden_required"
           on-click="@this.generate_po_file()"
           value="<?php echo $this->_pte('Update PO file'); ?>" />
</div>

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('Translate PO File')?></h3>
    </section>

    <div class="form-group row">
        <label for="language_to_translate" class="col-sm-2 col-form-label"><?php echo $this->_pt('Translate language'); ?></label>
        <div class="col-sm-10">
            <select name="language_to_translate" id="language_to_translate" value="{{language_to_translate}}">
                <option value=""> - <?php echo $this->_pt('Select language'); ?> - </option>
                {{#each @this.get_languages_arr() }}
                {{ #if .id !== '<?php echo LANG_EN?>' }}
                <option value="{{.id}}">{{.label}}</option>
                {{/if}}
                {{/each}}
            </select>
        </div>
    </div>

    <div class="form-group">
    <input type="button" id="do_translate_po_file" name="do_translate_po_file"
           class="btn btn-primary submit-protection ignore_hidden_required"
           on-click="@this.do_translate_po_file()"
           value="<?php echo $this->_pte('Translate PO file'); ?>" />
</div>
<div style="display: none;" id="phs_po_file_info_container">
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('PO file'); ?></label>
        <div class="col-sm-8">{{po_info_file}}</div>
    </div>
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Language'); ?></label>
        <div class="col-sm-8">{{@this.get_language_title(po_info.language)}} ({{po_info.language}})</div>
    </div>
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Total translations'); ?></label>
        <div class="col-sm-8">{{po_info.count}}</div>
    </div>
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Already translated'); ?></label>
        <div class="col-sm-8">{{po_info.translations_count}}</div>
    </div>
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Progress'); ?></label>
        <div class="col-sm-8">
            {{ po_info.translations_count }} / {{ po_info.count }}
            <div
                style="width:100%;height:24px;background-color: gray;"><div
                    class="phs_po_info_progress_bar"
                    style-width="{{@this.po_file_progress_percent_str()}}">{{@this.po_file_progress_percent_str()}}</div></div>
        </div>
    </div>

    <section class="heading-bordered">
        <h3><?php echo $this->_pt('AI Translation')?></h3>
    </section>

    {{ #if !ui_translation_status }}
    <p><?php echo $this->_pt('No status for UI translation using AI for this file.')?></p>
    {{ else }}
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Started'); ?></label>
        <div class="col-sm-8">{{@this.format_date_timestamp(ui_translation_status.started)}}</div>
    </div>
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Finished'); ?></label>
        <div class="col-sm-8">{{ui_translation_status.ended ? @this.format_date_timestamp(ui_translation_status.ended) : '-'}}</div>
    </div>
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Last update'); ?></label>
        <div class="col-sm-8">{{ui_translation_status.last_update ? @this.format_date_timestamp(ui_translation_status.last_update) : '-'}}</div>
    </div>
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Status'); ?></label>
        <div class="col-sm-8">{{ui_translation_status.status_title}}</div>
    </div>
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Progress'); ?></label>
        <div class="col-sm-8">
            {{ ui_translation_status.current_records }} / {{ ui_translation_status.max_records }}
            <div
                style="width:100%;height:24px;background-color: gray;"><div
                    class="phs_po_info_progress_bar"
                    style-width="{{@this.ui_progress_percent_str()}}">{{@this.ui_progress_percent_str()}}</div></div>
        </div>
    </div>
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Errors'); ?></label>
        <div class="col-sm-8">{{ui_translation_status.records_errors}}</div>
    </div>
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Success'); ?></label>
        <div class="col-sm-8">{{ui_translation_status.records_success}}</div>
    </div>
    <div class="form-group row">
        <label class="col-sm-4 col-form-label"><?php echo $this->_pt('Last log'); ?></label>
        <div class="col-sm-8">{{ui_translation_status.log}}</div>
    </div>

    {{ #if show_translation_force_stop }}
    <div class="form-group row">
        <div class="form-group">
            <input id="force_stop_translation" type="button" class="btn btn-danger"
                    on-click="@this.confirm_force_stop()" value="<?php echo $this->_pte('Force stop'); ?>" />
            {{ #if translation_is_stalling }}
            <small id="force_stop_translationHelp" class="form-text text-muted">
                <?php echo $this->_pt('Translation task hangs for %s. You might need to force stop it.', '{{ @this.seconds_to_time( stalling_seconds ) }}'); ?>
            </small>
            {{/if}}
        </div>
    </div>
    {{/if}}
    {{/if}}
</div>
</script>
<style>
.phs_po_info_progress_bar {
    height: 24px;
    background-color: green;
    text-align: center;
    color: white;
}
</style>
<script type="text/javascript">
var initial_translation_files = <?php echo @json_encode($translation_files);?>;
var initial_excluding_paths = <?php echo @json_encode($excluding_paths);?>;
var available_languages_arr = <?php echo @json_encode($available_languages_arr);?>;
const show_reset_timeout_seconds = 30;
let PHS_RActive_Ui_translations_app = null;
$(document).ready(function() {
    PHS_RActive_Ui_translations_app = PHS_RActive_Ui_translations_app || new PHS_RActive({

        target: "PHS_RActive_Ui_translations_target",
        template: "#PHS_RActive_Ui_translations_template",

        data: function () {
            return {
                excluding_paths: [],
                translation_files: {},

                language_to_update: '',
                language_to_translate: '',

                pot_filename: '',
                pot_modified: 0,
                pot_filesize: 0,
                pot_list_filename: '',
                pot_list_modified: 0,
                pot_list_filesize: 0,
                language_files: [],

                po_info_file: '',
                po_info: null,
                ui_translation_status: null
            }
        },

        oninit: function() {
            this.set('excluding_paths', initial_excluding_paths);
            this.set('translation_files', initial_translation_files);
            phs_refresh_input_skins();
        },

        observe: {
            'translation_files' (newVal) {
                this.populate_po_files_details(newVal);
            }
        },

        computed: {
            stalling_seconds () {
                const translation_status = this.get('ui_translation_status');
                if( translation_status !== null
                    && typeof translation_status.server_time !== "undefined"
                    && typeof translation_status.last_update !== "undefined" ) {
                    return parseInt(translation_status.server_time) - parseInt(translation_status.last_update);
                }

                return 0;
            },
            translation_is_stalling () {
                return (this.get('stalling_seconds') > show_reset_timeout_seconds);
            },
            show_translation_force_stop: function() {
                const translation_status = this.get('ui_translation_status');
                return translation_status !== null
                    && this.status_is_running( translation_status.status );
            }
        },

        status_has_error: function( status ) {
            return (status === <?php echo $ui_translations::STATUS_ERROR; ?>);
        },
        status_is_success: function( status ) {
            return (status === <?php echo $ui_translations::STATUS_FINISHED; ?>);
        },
        status_is_finished: function( status ) {
            return (status === <?php echo $ui_translations::STATUS_ERROR; ?>
                || status === <?php echo $ui_translations::STATUS_FINISHED; ?>
                || status === <?php echo $ui_translations::STATUS_FORCE_STOPPED; ?> );
        },
        status_is_running: function( status ) {
            return (status === <?php echo $ui_translations::STATUS_STARTING; ?>
                || status === <?php echo $ui_translations::STATUS_RUNNING; ?> );
        },

        get_languages_arr: function() {
            return available_languages_arr;
        },
        get_language_title: function(lang) {
            let details = this.get_language_details(lang);
            if( !details ) {
                return lang;
            }

            return details.label;
        },
        get_language_details: function(lang) {
            const languages = this.get_languages_arr();
            if( !languages || !languages.length
                || !lang || typeof lang !== "string"
                || !lang.length ) {
                return null;
            }

            for(let i = 0; i < languages.length; i++) {
                if(languages[i].id === lang) {
                    return languages[i];
                }
            }

            return null;
        },

        populate_po_files_details: function(details) {
            console.log("populating", details);
            this.set('pot_filename', details?.pot_file?.file ? details?.pot_file?.file : '');
            this.set('pot_modified', details?.pot_file?.modified ? details?.pot_file?.modified : 0);
            this.set('pot_filesize', details?.pot_file?.size ? details?.pot_file?.size : 0);
            this.set('pot_list_filename', details?.pot_list?.file ? details?.pot_list?.file : '');
            this.set('pot_list_modified', details?.pot_list?.modified ? details?.pot_list?.modified: 0);
            this.set('pot_list_filesize', details?.pot_list?.size ? details?.pot_list?.size: 0);
            this.set('language_files', details?.languages ? details?.languages : []);
        },

        add_directory_exception: function() {
            var excluding_paths = this.get('excluding_paths');
            excluding_paths.push('');

            this.set('excluding_paths', excluding_paths);
        },
        delete_excluding_path: function(index) {
            let excluding_paths = this.get("excluding_paths");
            if( !excluding_paths.length
                || typeof excluding_paths[index] === "undefined") {
                return;
            }

            if( (excluding_paths[index].length !== 0)
                && !confirm("<?php echo $this->_pte('Are you sure you want to remove this excluding path?')?>" ) ) {
                return;
            }

            excluding_paths.splice(index, 1);
            this.set("excluding_paths", excluding_paths);
        },

        po_file_info: function(file) {
            if( !file || !this._basename(file) ) {
                this.phs_add_error_message( "<?php echo $this->_pte('File does not exist.'); ?>", 10 );
                return;
            }

            const base_file = this._basename(file);

            let form_data = {};
            form_data.file = base_file;
            form_data.action = "do_po_info";

            let self = this;
            this._send_action_request(
                form_data,
                "<?php echo $this->_pte('Obtaining details...'); ?>",
                function(response){
                    if( typeof response.po_info === "undefined" ) {
                        self.phs_add_error_message( "<?php echo $this->_pte('Error obtaining PO file details. Please try again.'); ?>", 10 );
                        return;
                    }

                    self.set("ui_translation_status", response?.ui_translation_status);
                    self._display_po_file_info(base_file, response.po_info);

                    self.phs_add_success_message( "<?php echo $this->_pte('Displaying PO file info...');?>" );
                });
        },

        _display_po_file_info: function ( file, po_info ) {
            let container_obj = $("#phs_po_file_info_container");
            if( !container_obj || !container_obj.length ) {
                return;
            }

            this._set_po_file_info_data(po_info, file);

            container_obj.show();

            PHS_JSEN.createAjaxDialog( {
                suffix: 'phs_po_file_info_',
                width: 650,
                height: 700,
                title: "<?php echo $this->_pt('PO file info %s', '" + file + "'); ?>",
                resizable: true,
                source_not_cloned: true,
                close_outside_click: false,
                source_obj: container_obj,
                onbeforeclose: () => this._hide_po_file_info_dialogue()
            });
        },
        _hide_po_file_info_dialogue: function() {
            this._reset_po_file_info_data();
            let container_obj = $("#phs_po_file_info_container");
            if( !container_obj || !container_obj.length ) {
                return;
            }

            container_obj.hide();
        },
        _reset_po_file_info_data: function() {
            this._set_po_file_info_data(null, '');
        },
        _set_po_file_info_data: function(po_info, file) {
            this.set("po_info", po_info);
            this.set("po_info_file", file);
        },
        _po_file_progress_percent: function() {
            const po_info = this.get("po_info");
            if( po_info === null
                || typeof po_info.translations_count === "undefined"
                || typeof po_info.count === "undefined" ) {
                return 0;
            }

            if( po_info.count <= 0 ) {
                return 100;
            }

            return ((po_info.translations_count * 100) / po_info.count).toFixed( 1 );
        },
        po_file_progress_percent_str: function() {
            return this._po_file_progress_percent() + "%";
        },

        _ui_progress_percent: function() {
            const ui_translation_status = this.get("ui_translation_status");
            if( ui_translation_status === null
                || typeof ui_translation_status.current_records === "undefined"
                || typeof ui_translation_status.max_records === "undefined" ) {
                return 0;
            }

            if( ui_translation_status.max_records <= 0 ) {
                return 100;
            }

            return ((ui_translation_status.current_records * 100) / ui_translation_status.max_records).toFixed( 1 );
        },
        ui_progress_percent_str: function() {
            return this._ui_progress_percent() + "%";
        },

        trigger_download: function(file, size) {
            if( !file || !size ) {
                this.phs_add_error_message( "<?php echo $this->_pte('File does not exist.'); ?>", 10 );
                return;
            }

            this.download_file(this._basename(file));
        },

        download_file: function(file) {
            let form_data = {};
            form_data.file = file;
            form_data.action = "do_download";

            let self = this;
            this._send_action_request(
                form_data,
                "<?php echo $this->_pte('Preparing download...'); ?>",
                function(response){
                    if( typeof response.download_url === "undefined" ) {
                        self.phs_add_error_message( "<?php echo $this->_pte('Error obtaining a download link. Please try again.'); ?>", 10 );
                        return;
                    }

                    if( typeof response.file_size === "undefined" ) {
                        response.file_size = "<?php echo $this->_pte('unknown'); ?>";
                    }

                    self.phs_add_success_message( "<?php echo sprintf($this->_pte('Downloading file (%s bytes)...'), '" + response.file_size + "'); ?>" );

                    document.location = response.download_url;
                });
        },

        generate_pot_file: function() {
            let form_data = {};
            form_data.excluding_paths = this.get("excluding_paths");
            form_data.action = "do_regenerate_pot";

            let self = this;
            this._send_action_request(
                form_data,
                "<?php echo $this->_pte('Generating POT file...'); ?>",
                function(response){
                    self.phs_add_success_message( "<?php echo $this->_pte('POT file generated with success.'); ?>" );

                    if(response?.translation_files) {
                        self.set('translation_files', response.translation_files);
                    }
                });

        },

        generate_po_file: function() {
            let form_data = {};
            form_data.lang = this.get("language_to_update");
            form_data.action = "do_regenerate_po";

            let self = this;
            this._send_action_request(
                form_data,
                "<?php echo $this->_pte('Generating PO file...'); ?>",
                function(response){
                    self.phs_add_success_message( "<?php echo $this->_pte('PO file generated with success.'); ?>" );

                    if(response?.translation_files) {
                        self.set('translation_files', response.translation_files);
                    }
                });
        },

        confirm_force_stop: function() {
            if( !confirm("<?php echo $this->_pte('Are you sure you want to force stop the current translation task?')?>") ) {
                return;
            }

            const translation_status = this.get("ui_translation_status");
            if(!translation_status
                || typeof translation_status.language === "undefined"
                || !this.get("show_translation_force_stop") ) {
                this.phs_add_error_message( "<?php echo $this->_pte('No running translation task found.'); ?>" );
                return;
            }

            let form_data = {};
            form_data.lang = translation_status.language;
            form_data.action = "do_stop_translation";

            let self = this;
            this._send_action_request(
                form_data,
                "<?php echo $this->_pte('Sending stop request...'); ?>",
                function(response){

                    if( typeof response.ui_translation_status === "undefined" ) {
                        self.phs_add_error_message( "<?php echo $this->_pte('Error sending stop request. Please try again.'); ?>" );
                        return;
                    }

                    self.set("ui_translation_status", response.ui_translation_status);

                    self.phs_add_success_message( "<?php echo $this->_pte('Stop request sent to background task. Refresh PO file details to see the status.'); ?>" );
                });
        },

        do_translate_po_file: function() {
            let form_data = {};
            form_data.lang = this.get("language_to_translate");
            form_data.action = "do_translate_po_file";

            let self = this;
            this._send_action_request(
                form_data,
                "<?php echo $this->_pte('Translating PO file...'); ?>",
                function(response){
                    self.phs_add_success_message( "<?php echo $this->_pte('PO translation task launched with success in background job.'); ?>" );
                    self.phs_add_success_message( "<?php echo $this->_pte('You can check the progress by clicking the <i class="fa fa-info"></i> icon for provided language.'); ?>" );
                });
        },

        _basename: function(file) {
            return file.split('/').reverse()[0];
        },

        _send_action_request: function(form_data, loader_message, success_callback, failure_callback) {
            const self = this;

            if( typeof success_callback === "undefined" ) {
                success_callback = null;
            }
            if( typeof failure_callback === "undefined" ) {
                failure_callback = null;
            }
            if( typeof loader_message === "undefined" || !loader_message ) {
                loader_message = "<?php echo $this->_pte('Please wait...'); ?>";
            }

            show_submit_protection( loader_message );

            this.read_data(
                "<?php echo PHS::route_from_parts(['a' => 'ui_translations', 'ad' => 'translations', 'c' => 'api', 'p' => 'admin']); ?>",
                form_data,
                function( response, status, ajax_obj, data ) {

                    hide_submit_protection();

                    if( !self.valid_default_response_from_read_data( response ) ) {
                        let error_msg = "<?php echo $this->_pte('Error sending request to server.'); ?>";
                        const extra_error = self.get_error_message_for_default_read_data(response);
                        if( extra_error ) {
                            error_msg += " " + extra_error;
                        }

                        if( $.isFunction( failure_callback ) ) {
                            failure_callback(self, error_msg);
                        } else if( typeof failure_callback == "string" ) {
                            eval(failure_callback + "( self, error_msg )");
                        } else {
                            self.phs_add_error_message(error_msg, 10);
                        }

                        return;
                    }

                    if( success_callback ) {
                        success_callback( response.response );
                    }
                },
                function( ajax_obj, status, error_exception ) {
                    hide_submit_protection();

                    const error_msg = "<?php echo $this->_pte('Error sending request to the server. Please try again.'); ?>";

                    if( $.isFunction( failure_callback ) ) {
                        failure_callback(self, error_msg);
                    } else if( typeof failure_callback == "string" ) {
                        eval(failure_callback + "( self, error_msg )");
                    } else {
                        self.phs_add_error_message(error_msg, 10);
                    }
                }, {
                    queue_request: false,
                    queue_response_cache: false,
                    queue_response_cache_timeout: 5,
                    stack_request: true
                }
            );
        }

    });
});
</script>
<?php
