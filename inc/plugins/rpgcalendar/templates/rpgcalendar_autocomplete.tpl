<link rel="stylesheet" href="{$mybb->asset_url}/jscripts/select2/select2.css?ver=1807" />
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/select2/select2.min.js?ver=1806"></script>
<script type="text/javascript">
<!--
if(typeof use_xmlhttprequest !== "undefined" && use_xmlhttprequest == "1")
{
	if(typeof MyBB !== "undefined" && typeof MyBB.select2 === "function") { MyBB.select2(); }
	$("{$ac_target}").select2({
		placeholder: "{$lang->rpgcalendar_ac_placeholder}",
		minimumInputLength: 2,
		multiple: {$ac_multiple},
		ajax: {
			url: "xmlhttp.php?action=get_users",
			dataType: 'json',
			data: function (term, page) { return { query: term }; },
			results: function (data, page) { return { results: data }; }
		},
		initSelection: function(element, callback) {
			var query = $(element).val();
			if (query !== "") {
				var newqueries = [];
				var exp_queries = query.split(",");
				$.each(exp_queries, function(index, value){
					if(value.replace(/\s/g, '') != "") {
						newqueries.push({ id: value.replace(/,\s?/g, ", "), text: value.replace(/,\s?/g, ", ") });
					}
				});
				callback(newqueries);
			}
		}
	});
}
// -->
</script>
