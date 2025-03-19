<?php
/*
 |  tail.writer 4 Bludit
 |  @file       ./plugin.php
 |  @author     SamBrishes <sam@pytes.net>
 |  @version    0.4.0 [0.3.2] - Alpha
 |
 |  @website    https://github.com/pytesNET/tail.writer-bludit
 |  @license    X11 / MIT License
 |  @copyright  Copyright © 2019 pytesNET <info@pytes.net>
 */

	class PluginTailWriter extends Plugin{
		/*
		 |	LIST OF CONTROLLERS TO LOAD
		 */
		private $loadOnController = array(
			"new-content", "edit-content"
		);

		/*
		 |	HELPER :: SELECT OPTION
		 |	@version	0.4.0 [0.4.0]
		 */
		public function selected($value, $compare, $echo = true){
			if($this->getValue($value) == $compare){
				$selected = 'selected="selected"';
			} else {
				$selected = '';
			}
			if(!$echo){
				return $selected;
			}
			print($selected);
		}

		/*
		 |	HOOK :: INIT PLUGIN
		 |	@version	0.4.0 [0.4.0]
		 */
		public function init(){
			$this->dbFields = array(
				"markup"	=> "markdown",
				"locale"  	=> "en",
				"design"	=> "github"
			);
		}

		/*
		 |	HOOK :: ADMIN HEAD
		 |	@version	0.4.0 [0.3.2]
		 */
		public function adminHead(){
			if(!in_array($GLOBALS["ADMIN_CONTROLLER"], $this->loadOnController)){
				return false;
			}

			ob_start();
			?>
				<link type="text/css" rel="stylesheet" href="<?php echo $this->htmlPath(); ?>css/tail.writer-<?php echo $this->getValue("design"); ?>.min.css" />
				
				<style type="text/css">
					.tail-writer{
						margin: 15px 0 0 0;
					}
					.tail-writer textarea#jseditor{
					    width: 100%;
						height: auto;
						min-height: 300px;
						max-height: inherit !important;
						padding: 20px !important;
						font-size: 16px;
						line-height: 24px;
						border: 1px solid #d1d5da;
						background-color: #f6f8fa;
					}
					.tail-writer textarea#jseditor:hover{
						border-color: #c1c5ca;
						background-color: #f6f8fa;
					}
					.tail-writer textarea#jseditor:focus{
						border-color: #2188ff;
						background-color: #ffffff;
					}
				</style>

				<script type="text/javascript" src="<?php echo $this->htmlPath(); ?>js/showdown.js"></script>
				<script type="text/javascript" src="<?php echo $this->htmlPath(); ?>js/showdown.ghalerts.js"></script>
				<script type="text/javascript" src="<?php echo $this->htmlPath(); ?>js/tail.writer-full.js"></script>
				<script type="text/javascript">
					tail.writer.hook("init", "bludit", function(){
						this.e.editor.className = this.e.editor.className.replace("h-100", "");
						this.e.editor.focus();
					});
				</script>
			<?php
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}

		/*
		 |	HOOK :: ADMIN HEAD
		 |	@version	0.4.0 [0.3.3]
		 */
		public function adminBodyEnd(){
			if(!in_array($GLOBALS["ADMIN_CONTROLLER"], $this->loadOnController)){
				return false;
			}

			ob_start();
			?>
				<script type="text/javascript">
					var WriterEditor = null;

					function editorInsertMedia(file){
						WriterEditor.writeContent("![" + WriterEditor.translate("imageTitle") + "](" + file + ")");
						$("#jsmediaManagerModal").on("hidden.bs.modal", function(){
							WriterEditor.e.editor.focus();
						});
					}

					function editorGetContent(){
						return WriterEditor.getContent();
					}

					document.addEventListener("DOMContentLoaded", function(){
						WriterEditor = tail.writer("#jseditor", {
							height: ["400px", "700px"],
							toolbar: "full",
							locale: "en",
							markup: "markdown",
						});
					});
				</script>
			<?php
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}
	}
