{*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
<style>

.panel-heading {
	display:none !important;
}

#module_form_submit_btn {
    display: block !important;
}

#fieldset_0 {
    background: #EFF1F2 none repeat scroll 0 0 !important;
    border: 1px solid #EFF1F2 !important;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.00) !important;
}

#fieldset_0 .panel-heading, #fieldset_0 .panel-footer {
    background: #EFF1F2 none repeat scroll 0 0 !important;
    border: 1px solid #EFF1F2 !important;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.00) !important;
}

.col-lg-9 .checkbox{
	display: inline-block;
	width: 33%;
	float: left;
	min-height: 46px !important;
	padding-right: 5px;
}

</style><div class="panel">
	<h3><i class="icon icon-warning"></i> {l s='Productos para reponer' mod='productosvendidos'}</h3>
	<p> 
		<span style="font-size: 15px;">Pulsa en GENERAR PDF para descargar un archivo con los productos a reponer o comprobar, contenidos en pedidos del período de tiempo seleccionado.</span>
		<br/>
	</p>
</div>

<script>
	$( document ).ready(function() {
		
		//poner el checkbox Ahora checado por defecto y darle valor 1 a oculto_hasta
		$('#hasta_ahora').prop('checked', true);
		$('#oculto_hasta').val(1);
		//poner deshabilitado el datetime de hasta mientras #hasta_ahora esté checado
		$('#productosvendidos_HASTA').prop('disabled', true);	
		
		//inicializar el checkbox de Guardar fecha_hasta como checado por defecto
		$('#guarda_fecha').prop('checked', true);
		//inicializar valor de oculto_guarda a 1, si se desmarca el check de 'guardar fecha hasta' se pasará a 0, de modo que por defecto guardara la fecha_hasta
		$('#oculto_guarda').val(1);

		//si cambia #hasta_ahora, es decir, pasa de checked a no checked, se habilita o deshabilita el datetime #productosvendidos_HASTA
		$('#hasta_ahora').change(function () {
		    if($('#hasta_ahora').prop('checked') == false){
		    	$('#productosvendidos_HASTA').prop('disabled', false);
		    	$('#oculto_hasta').val(0);
		    }else{
		    	$('#productosvendidos_HASTA').prop('disabled', true);
		    	$('#oculto_hasta').val(1);
		    }
		 });
		
		//si cambia #guarda_fecha, es decir, pasa de checked a no checked, se cambia el valor de #oculto_guarda de 1 a 0 y viceversa
		$('#guarda_fecha').change(function () {
		    if($('#guarda_fecha').prop('checked') == true){		    	
		    	$('#oculto_guarda').val(1);
		    }else{		    	
		    	$('#oculto_guarda').val(0);
		    }
		 });

		//ponemos valor inical a los campos date con la fecha de hoy
	    var hoy = new Date();
		var dd = hoy.getDate();
		var mm = hoy.getMonth()+1;
		var yyyy = hoy.getFullYear();
		
		if(dd<10) {
		    dd = '0'+dd
		} 
		
		if(mm<10) {
		    mm = '0'+mm
		} 

		//sacar horas minutos y segundos y poner 0 delante si no lo tiene y debe tenerlo
		function addZero(i) {
		    if (i < 10) {
		        i = "0" + i;
		    }
		    return i;
		}
			    
	    var h = addZero(hoy.getHours());
	    var m = addZero(hoy.getMinutes());
	    var s = addZero(hoy.getSeconds());			
		
		hoy = yyyy+'-'+mm+'-'+dd+' '+h + ':' + m + ':' + s;		    
	    
	    //asignar la fecha y hora actual al datepicker Hasta:
	    $('#productosvendidos_HASTA').val(hoy);

	    
	    //Para el valor inicial de productosvendidos_DESDE queremos poner el del último informe que se hizo, esta fecha la he metido como value de varios inputs hidden en el formulario, uno para Tienda, otro para Devoluciones, otro para Productos Preparados, etc. Hay que sacar con JQuery la fecha de esos hidden y metersela al datepicker productosvendidos_DESDE según el radiobutton que esté pulsado. Por defecto al cargar estará Tienda, luego al cargar saldrá ese y al cambiar a otro se cambiará.	    
	    var fecha_hasta_tienda = $('#aux_tienda').val();

	    $('#productosvendidos_DESDE').val(fecha_hasta_tienda); //fecha inicial en el datepicker

	    //ahora, si pulsamos otro radiobutton, queremos que la fecha que salga por defecto en el datepicker fecha_desde sea la fecha_hasta que se usó con ese tipo de busqueda y se guardó la última vez, y así con cada radiobutton, incluido el de tienda si se vuelve a él:
	    
	    //pulsando radiobutton de TIENDA
	    $('#tpv').change(function () {
		    if($('#tpv').prop('checked') == true){
		    	$('#productosvendidos_DESDE').val(fecha_hasta_tienda);
		    }
		 });

		var fecha_hasta_devolucion = $('#aux_devolucion').val();
	    //pulsando radiobutton de DEVOLUCIONES
	    $('#devos').change(function () {
		    if($('#devos').prop('checked') == true){
		    	$('#productosvendidos_DESDE').val(fecha_hasta_devolucion);
		    }
		 });

		var fecha_hasta_preparados = $('#aux_preparados').val();
	    //pulsando radiobutton de PRODUCTOS PREPARADOS
	    $('#preparados').change(function () {
		    if($('#preparados').prop('checked') == true){
		    	$('#productosvendidos_DESDE').val(fecha_hasta_preparados);
		    }
		 });
 	   
	   //19/07/2019 Vamos a añadir un nuevo Radio Button para sacar los productos dentro de pedidos en espera (prepedidos, reservando, sin stock pagados y esperando productos) para hacer un control del stock. Se seguirán guardando las fechas como orientación de cuando se hizo la última revisión si se mantiene pulsado Guardar fecha hasta.
	   var fecha_hasta_prepedidos = $('#aux_prepedidos').val();

	   $('#prepedidos').change(function () {
		    if($('#prepedidos').prop('checked') == true){
		    	$('#productosvendidos_DESDE').val(fecha_hasta_prepedidos);
		    }
		 });

	   //21/08/2019 Vamos a añadir un nuevo Radio Button para sacar los productos transferidos desde almacén físico Múgica hasta el almacén online. Se seguirán guardando las fechas de cuando se hizo la última revisión si se mantiene pulsado Guardar fecha hasta.
	   var fecha_hasta_transferidos = $('#aux_transferidos').val();

	   $('#transferidos').change(function () {
		    if($('#transferidos').prop('checked') == true){
		    	$('#productosvendidos_DESDE').val(fecha_hasta_transferidos);
		    }
		 });

		//24/07/2020 Vamos a añadir un nuevo Radio Button para sacar los productos contenidos en packs nativos vendidos entre las fechas seleccionadas. Se seguirán guardando las fechas de cuando se hizo la última revisión si se mantiene pulsado Guardar fecha hasta.
	   var fecha_hasta_packsnativos = $('#aux_packsnativos').val();

	   $('#packsnativos').change(function () {
		    if($('#packsnativos').prop('checked') == true){
		    	$('#productosvendidos_DESDE').val(fecha_hasta_packsnativos);
		    }
		 });

		 //19/10/2020 Vamos a añadir un nuevo Radio Button para sacar los productos con stock online y localización de reposición válida. Se seguirán guardando las fechas de cuando se hizo la última revisión si se mantiene pulsado Guardar fecha hasta.
		 //14/05/2021 lo quitamos de momento
	   /*var fecha_hasta_loc_repos = $('#aux_loc_repos').val();

	   $('#loc_repos').change(function () {
		    if($('#loc_repos').prop('checked') == true){
		    	$('#productosvendidos_DESDE').val(fecha_hasta_loc_repos);
		    }
		 });*/

		//17/12/2020 Vamos a añadir un nuevo Radio Button para sacar los productos descatalogados con stock. Se seguirán guardando las fechas de cuando se hizo la última revisión si se mantiene pulsado Guardar fecha hasta.
		//13/05/2021 lo quitamos de momento
	   /*var fecha_hasta_descatalogadosconstock = $('#aux_descatalogadosconstock').val();

	   $('#descatalogadosconstock').change(function () {
		    if($('#descatalogadosconstock').prop('checked') == true){
		    	$('#productosvendidos_DESDE').val(fecha_hasta_descatalogadosconstock);
		    }
		 });*/

		 //30/03/2021 Vamos a añadir un nuevo Radio Button para sacar los productos que han cambiado su tipo ABC entre las fechas escogidas
	   //var fecha_hasta_cambio_abc = $('input:eq(26)').attr('id');
	   var fecha_hasta_cambio_abc = $('#aux_cambio_abc').val();

	   $('#cambio_abc').change(function () {
		    if($('#cambio_abc').prop('checked') == true){
		    	$('#productosvendidos_DESDE').val(fecha_hasta_cambio_abc);
		    }
		 });

		 //14/05/2021 Vamos a añadir un nuevo Radio Button para sacar los productos tipo C con stock, con loc repo en estanteria 2 o más vendidos entre las fechas escogidas	   
	   var fecha_hasta_reposicion_c = $('#aux_reposicion_c').val();

	   $('#reposicion_c').change(function () {
		    if($('#reposicion_c').prop('checked') == true){
		    	$('#productosvendidos_DESDE').val(fecha_hasta_reposicion_c);
		    }
		 });

		 //18/11/2022 Vamos a añadir un nuevo Radio Button para sacar los productos vendidos A y B con stock,  entre las fechas escogidas y con localización de reposición   
	   var fecha_hasta_reposicion_a_b = $('#aux_reposicion_a_b').val();

	   $('#reposicion_a_b').change(function () {
		    if($('#reposicion_a_b').prop('checked') == true){
		    	$('#productosvendidos_DESDE').val(fecha_hasta_reposicion_a_b);
		    }
		 });


	});
</script>
