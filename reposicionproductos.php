<?php
/**
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
*/

// A PARTIR DE 18/07/2019 modifiqué este php para en lugar de sacar los productos para reponer zona de picking(obsoleto) sacar los productos que estaban en pedidos que habían entrado en estado Devolución Recibida en unas determinadas fechas, para controlar su stock
// 19/07/2019 Añadido para productos en pedidos con prepedido (control de stock de los productos en esos pedidos)
// 21/08/2019 Nuevo informe que saque los productos que se han transferido desde el almacén de Tienda Múgica al almacén Tienda Online
// 17/12/2020 informe con productos descatalogados que tienen stock
// 30/03/2021 informe que muestra los productos que han cambiado de tipo ABC en un período de tiempo, para relocalizarlos


require_once(_PS_TOOL_DIR_.'tcpdf/tcpdf.php');

if (!defined('_PS_VERSION_')) {
    exit;
}


	
	 class MY_PDF_REPO extends TCPDF {
	    //Page header
	    public function Header() {    
	        // Logo
	        /*$image_file = K_PATH_IMAGES.'logo_mail.jpg';
	        $this->Image($image_file, 15, 10, '25', '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);*/
	        // Set font
	        $this->SetFont('helvetica', 'B', 12);
	        // Title
	        
	        //Para poner en el header del pdf lo que muestra la lista llamamos a los valores del formulario
	        SWITCH (Tools::getValue('informe')){	        
	        CASE '0':	           
	            $informe = 'TIENDA';
	            break;
	        CASE '1':	            
	            $informe = 'DEVOLUCIONES';
	            break;
	        CASE '2':	            
	            $informe = 'PRODUCTOS PREPARADOS';
	            break;
	        CASE '3':	            
	            $informe = 'PRODUCTOS EN ESPERA';
	            break;
	        CASE '4':	            
	            $informe = 'PRODUCTOS TRANSFERIDOS';
				break;
			CASE '5':	            
	            $informe = 'PRODUCTOS EN PACKS NATIVOS';
				break;
			CASE '6':	            
	            $informe = 'PRODUCTOS CON LOCALIZACIÓN DE REPOSICIÓN';
				break;
			CASE '7':	            
	            $informe = 'PRODUCTOS DESCATALOGADOS CON STOCK';
	            break;
			CASE '8':	            
	            $informe = 'CAMBIO ABC';
	            break;
			CASE '9':	            
	            $informe = 'REPOSICIÓN C';
	            break;
	    	}
	    	//la fecha hasta la cogemos dependiendo de si está marcado el check de Hasta ahora o del datepicker, si está marcado el check cogemos NOW o date
	    	if (Tools::getValue('oculto_hasta')){    		
	    		$fecha_hasta = date("Y-m-d H:i:s");
	    		
	    	} else {
	    		$fecha_hasta = Tools::getValue('productosvendidos_HASTA');
	    	}

	    	//la fecha desde la cogemos del datepicker, ya que si la hemos metido en el datepicker manualmente, ya la tenemos ahí, y si se ha sacado de base de datos, también se ha puesto ya como valor en el datepicker
	    	$fecha_desde = Tools::getValue('productosvendidos_DESDE');
	    	
			$this->Cell(0, 15, "Reposición - ".$informe." - entre '".date("d-m-Y H:i:s", strtotime($fecha_desde))."' y '".date("d-m-Y H:i:s", strtotime($fecha_hasta))."' ", 0, false, 'R', 0, '', 0, false, 'T', 'M');
			                   
	    }
	 }


class ReposicionProductos extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'reposicionproductos';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Sergio (bueno, más o menos)';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Reposición de Productos');
        $this->description = $this->l('Genera un PDF con los productos en pedidos en un determinado estado entre fechas, para su reposición o consultar el stock.');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('REPOSICIONPRODUCTOS_LIVE_MODE', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader');
    }

    public function uninstall()
    {
        Configuration::deleteByName('REPOSICIONPRODUCTOS_LIVE_MODE');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitReposicionProductosModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitReposicionProductosModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */            
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
    	
		$ultima = array(
		 		array(
				'id' => 'ultima'
				)	 
			);
		$ahora = array(
		 		array(
				'id' => 'ahora'
				)
			);
		$guardafecha = array(
		 		array(
				'id' => 'fecha'
				)
			);
		

        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l(' Generar PDF de productos a reponer o comprobar'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(             

                    array(
			            'type' => 'datetime',			            
			            'label' => $this -> l('Desde:'), 
			            'name' => 'productosvendidos_DESDE', 			            
			            'size' => 10, 
			            'required' => true, 
			            'hint' => $this -> l('Fecha desde la que quieres obtener los productos vendidos, transferidos, a reponer o comprobar'), 
			        ), 
                    
			        array('type' => 'datetime', 			        	
			            'label' => $this -> l('Hasta:'), 
			            'name' => 'productosvendidos_HASTA', 
			            'size' => 10, 
			            'required' => true, 
			            'hint' => $this -> l('Fecha hasta la que quieres obtener los productos vendidos, transferidos, a reponer o comprobar'), 
			        ),
                 
	                array(
	                    'type' => 'checkbox',
	                    'label' => $this->l('Hasta ahora'),
	                    'name' => 'hasta',
	                    'hint' => $this -> l('Sacar la lista hasta este instante, quitar check para poner otra fecha'), 
	                    'values' => array(	                        
	                        'query' => $ahora,	
	                        'id' => 'id'                                                                  
	                    ),
	                ),

	                array(
	                    'type' => 'checkbox',
	                    'label' => $this->l('Guardar fecha Hasta'),
	                    'name' => 'guarda',
	                    'hint' => $this -> l('Quitar check para no guardar la fecha Hasta'), 
	                    'values' => array(	                        
	                        'query' => $guardafecha,	
	                        'id' => 'id'                                                                  
	                    ),
	                ),

					array(
			            'type' => 'radio',
			            'label' => $this->l('Informe:'),
			            'name' => 'informe',            
			            'values' => array(                                                             
			             
			             array(
			                   'id' => 'tpv',
			                   'value' => 0,			                   
			                   'label' => $this->l('TIENDA (Productos vendidos en tienda con localización de picking)')
			             ),			            
			            array(
			                   'id' => 'devos',
			                   'value' => 1,			                   
			                   'label' => $this->l('DEVOLUCIONES (Productos en pedidos que pasaron a estado Devolución Recibida en las fechas indicadas)')
			             ),
			             array(
			                   'id' => 'preparados',
			                   'value' => 2,			                   
			                   'label' => $this->l('PRODUCTOS PREPARADOS (Mostrará solo productos preparados vendidos)')
			             ),
			             array(
			                   'id' => 'prepedidos',
			                   'value' => 3,			                   
			                   'label' => $this->l('PRODUCTOS EN ESPERA (Mostrará productos en pedidos en espera con productos prepedidos)')
			             ),
			             array(
			                   'id' => 'transferidos',
			                   'value' => 4,			                   
			                   'label' => $this->l('PRODUCTOS TRANSFERIDOS (Mostrará productos transferidos desde Almacén Tienda Múgica hasta Almacén Tienda Online)')
						 ),
						 array(
								'id' => 'packsnativos',								
								'value' => 5,			                   
								'label' => $this->l('PRODUCTOS EN PACKS (Mostrará los productos dentro de los packs vendidos entre las fechas seleccionadas)')
						 ),
						  //14/05/2021 lo quitamos de momento
						//  array(
						// 		'id' => 'loc_repos',								
						// 		'value' => 6,			                   
						// 		'label' => $this->l('PRODUCTOS CON LOCALIZACIÓN DE REPOSICIÓN (Mostrará los productos que tienen stock online y una localización de reposición almacenada)')
						//  ),
						 //13/05/2021 lo quitamos de momento
						//  array(
						// 		'id' => 'descatalogadosconstock',																
						// 		'value' => 7,			                   
						// 		'label' => $this->l('PRODUCTOS DESCATALOGADOS CON STOCK (Mostrará los productos que están descatalogados pero tienen stock disponible. Ud. indica el stock disponible, Online y Tienda indica el stock físico en almacén.)')
						//  ),
						 array(
								'id' => 'cambio_abc',								
								'value' => 8,			                   
								'label' => $this->l('CAMBIO ABC (Mostrará los productos que han cambiado de tipo ABC entre las fechas seleccionadas)')
						 ),
						 array(
								'id' => 'reposicion_c',								
								'value' => 9,			                   
								'label' => $this->l('REPOSICIÓN PRODUCTOS C (Mostrará los productos vendidos entre las fechas seleccionadas con clasificación C, stock físico y localizados a partir de 2ª altura o con dos localizaciones de reposición)')
						)
			              ),
			             'is_bool' => true,                                              
			            'required' => true
			           ),
					
					array(
						'type' => 'hidden',						
						'id' => 'oculto_hasta',
						'name' => 'oculto_hasta'
					),

					array(
						'type' => 'hidden',						
						'id' => 'oculto_guarda',
						'name' => 'oculto_guarda'
					),
							//Estos hidden de debajo me sirven para almacenar en cada uno la última fecha_hasta con la que se sacó cada informe respectivo (siempre y cuando se guardara en base de datos (tabla frik_informes_reposicion)) que obtengo arriba, y poder coger de ahí la fecha para poner mediante JQuery (en configure.tpl) la fecha en el datepicker de fecha_desde cuando se selecciona un tipo de informe determinado y que ya aparezca por defecto. Si se quiere usar otra fecha habrá que hacerlo manualmente usando el datepicker
							//19/07/2019 Añado otro para fecha hasta de productos en pedidos prepedidos
							//21/08/2019 Añado otro para fecha hasta de productos transferidos desde tienda Múgica
							//23/07/2020 Añado otro para fecha hasta de productos en packs vendidos
							//19/10/2020 Añado otro para fecha hasta de productos con localización de reposición
							//17/12/2020 Añado otro para fecha hasta de productos con descatalogados con stock
							//30/03/2021 Añado otro para fecha hasta de productos cambio ABC
							//15/05/2021 Añado otro para fecha hasta de productos reposición tipo C
					
					array(
						'type' => 'hidden',						
						'id' => 'aux_tienda',
						'name' => 'aux_tienda'																							
					),

					array(
						'type' => 'hidden',						
						'id' => 'aux_devolucion',
						'name' => 'aux_devolucion'																							
					),

					array(
						'type' => 'hidden',						
						'id' => 'aux_preparados',
						'name' => 'aux_preparados'																							
					),

					array(
						'type' => 'hidden',						
						'id' => 'aux_prepedidos',
						'name' => 'aux_prepedidos'																							
					),

					array(
						'type' => 'hidden',						
						'id' => 'aux_transferidos',
						'name' => 'aux_transferidos'																							
					),

					array(
						'type' => 'hidden',												
						'id' => 'aux_packsnativos',
						'name' => 'aux_packsnativos'																						
					),
					//14/05/2021 lo quitamos de momento
					// array(
					// 	'type' => 'hidden',												
					// 	'id' => 'aux_loc_repos',
					// 	'name' => 'aux_loc_repos'																						
					// ),
					//13/05/2021 lo quitamos de momento
					// array(
					// 	'type' => 'hidden',												
					// 	'id' => 'aux_descatalogadosconstock',
					// 	'name' => 'aux_descatalogadosconstock'																						
					// ),

					array(
						'type' => 'hidden',												
						'id' => 'aux_cambio_abc',
						'name' => 'aux_cambio_abc',				
					),

					array(
						'type' => 'hidden',												
						'id' => 'aux_reposicion_c',
						'name' => 'aux_reposicion_c',				
					),

			       ),

                'submit' => array(
                	'id' => 'genera',
                    'title' => $this->l('Generar PDF'),
                ),
                
                ),
                
            );
        
		
    }

    /**
     * Set values for the inputs.
     */
   
    protected function getConfigFormValues() {

    	//si ocultodesde está checado se sacará la fechadesde desde la base de datos, la última consultada, sino, la del datetime
    	if (Tools::getValue('oculto_desde')){
    		//preparar consulta y sacar fecha del último informe del tipo correspondiente
    		//sacar el idtipo para saber de que tipo de consulta queremos la fecha del último informe
    		SWITCH (Tools::getValue('informe')){
	        CASE '0':
	            $idtipo = 0;
	            break;
	        CASE '1':
	            $idtipo = 1;
	            break;
	        CASE '2':
	            $idtipo = 2;
	            break;	
	        CASE '3':
	            $idtipo = 3;
	            break;   
	        CASE '4':
	            $idtipo = 4;
				break; 
			CASE '5':
	            $idtipo = 5;
				break;  
			CASE '6':
	            $idtipo = 6;
				break;   
			CASE '7':
	            $idtipo = 7;
	            break;  
			CASE '8':
	            $idtipo = 8;
	            break; 
			CASE '9':
	            $idtipo = 9;
	            break;  
	    	}
	    	//consulta a base de datos para sacar la fecha_hasta del tipo de informe correspondiente. Busca la última ordenando de más vieja a más nueva por id_informe
	    	$sql = "SELECT fecha_hasta AS fecha FROM frik_informes_reposicion WHERE id_tipo = ".$idtipo." ORDER BY id_informe DESC LIMIT 1;";
			$results = Db::getInstance()->ExecuteS($sql);
			foreach ($results as $resultado) {
				$date_desde = $resultado['fecha'];
			}
    		
    	} else {
    		$date_desde = Tools::getValue('productosvendidos_DESDE');    		
    	}

    	//si ocultohasta está checado (ahora) se pone la fechahasta como NOW (date), si no, se coge la del datetime
    	if (Tools::getValue('oculto_hasta')){    		
    		$date_hasta = date("Y-m-d H:i:s");
    		
    	} else {
    		$date_hasta = Tools::getValue('productosvendidos_HASTA');
    	}

		//para poner la última fecha en que se hizo el informe en el value de los input hidden que usaremos para el datepicker, asignamos aquí al 'name' de cada input su fecha sacada de la BD	
		//TIENDA
		$sql = "SELECT fecha_hasta AS fecha FROM frik_informes_reposicion WHERE id_tipo = 0 ORDER BY id_informe DESC LIMIT 1;";
		$results = Db::getInstance()->ExecuteS($sql);
		foreach ($results as $resultado) {			
			$aux_tienda = $resultado['fecha'];
		}

		//Productos en DEVOLUCIÓN
		$sql = "SELECT fecha_hasta AS fecha FROM frik_informes_reposicion WHERE id_tipo = 1 ORDER BY id_informe DESC LIMIT 1;";
		$results = Db::getInstance()->ExecuteS($sql);
		foreach ($results as $resultado) {			
			$aux_devolucion = $resultado['fecha'];
		}

		//PREPARADOS
		$sql = "SELECT fecha_hasta AS fecha FROM frik_informes_reposicion WHERE id_tipo = 2 ORDER BY id_informe DESC LIMIT 1;";
		$results = Db::getInstance()->ExecuteS($sql);
		foreach ($results as $resultado) {			
			$aux_preparados = $resultado['fecha'];
		}

		//PREPEDIDOS en espera  19/07/2019
		$sql = "SELECT fecha_hasta AS fecha FROM frik_informes_reposicion WHERE id_tipo = 3 ORDER BY id_informe DESC LIMIT 1;";
		$results = Db::getInstance()->ExecuteS($sql);
		foreach ($results as $resultado) {			
			$aux_prepedidos = $resultado['fecha'];
		}

		//PRODUCTOS transferidos desde tienda Física a Tienda Online  21/08/2019
		$sql = "SELECT fecha_hasta AS fecha FROM frik_informes_reposicion WHERE id_tipo = 4 ORDER BY id_informe DESC LIMIT 1;";
		$results = Db::getInstance()->ExecuteS($sql);
		foreach ($results as $resultado) {			
			$aux_transferidos = $resultado['fecha'];
		}

		//PRODUCTOS dentro de Packs nativos vendidos en período de tiempo  23/07/2020
		$sql = "SELECT fecha_hasta AS fecha FROM frik_informes_reposicion WHERE id_tipo = 5 ORDER BY id_informe DESC LIMIT 1;";
		$results = Db::getInstance()->ExecuteS($sql);
		foreach ($results as $resultado) {			
			$aux_packsnativos = $resultado['fecha'];
		}

		//PRODUCTOS con stock online y localización de reposición  19/10/2020
		//14/05/2021 lo quitamos de momento
		// $sql = "SELECT fecha_hasta AS fecha FROM frik_informes_reposicion WHERE id_tipo = 6 ORDER BY id_informe DESC LIMIT 1;";
		// $results = Db::getInstance()->ExecuteS($sql);
		// foreach ($results as $resultado) {			
		// 	$aux_loc_repos = $resultado['fecha'];
		// }

		//PRODUCTOS descatalogados con stock  17/12/2020
		//13/05/2021 lo quitamos de momento
		// $sql = "SELECT fecha_hasta AS fecha FROM frik_informes_reposicion WHERE id_tipo = 7 ORDER BY id_informe DESC LIMIT 1;";
		// $results = Db::getInstance()->ExecuteS($sql);
		// foreach ($results as $resultado) {			
		// 	$aux_descatalogadosconstock = $resultado['fecha'];
		// }

		//PRODUCTOS Cambio ABC 30/03/2021
		$sql = "SELECT fecha_hasta AS fecha FROM frik_informes_reposicion WHERE id_tipo = 8 ORDER BY id_informe DESC LIMIT 1;";
		$results = Db::getInstance()->ExecuteS($sql);
		foreach ($results as $resultado) {			
			$aux_cambio_abc = $resultado['fecha'];
		}

		//PRODUCTOS Reposición C 14/05/2021
		$sql = "SELECT fecha_hasta AS fecha FROM frik_informes_reposicion WHERE id_tipo = 9 ORDER BY id_informe DESC LIMIT 1;";
		$results = Db::getInstance()->ExecuteS($sql);
		foreach ($results as $resultado) {			
			$aux_reposicion_c = $resultado['fecha'];
		}
    	
        return array(            
            "productosvendidos_DESDE" => $date_desde, 
            "productosvendidos_HASTA" => $date_hasta,
			'aux_tienda' => $aux_tienda,
			'aux_devolucion' => $aux_devolucion,
			'aux_preparados' => $aux_preparados,
			'aux_prepedidos' => $aux_prepedidos,
			'aux_transferidos' => $aux_transferidos,
			'aux_packsnativos' => $aux_packsnativos,
			// 'aux_loc_repos' => $aux_loc_repos, //14/05/2021 lo quitamos de momento
			// 'aux_descatalogadosconstock' => $aux_descatalogadosconstock,  //13/05/2021 lo quitamos de momento
			'aux_cambio_abc' => $aux_cambio_abc,
			'aux_reposicion_c' => $aux_reposicion_c,
            "informe" => Configuration::get('informe')                           
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess() {
        $form_values = $this -> getConfigFormValues(); 
        
        $form_fecha_desde = "'".$form_values['productosvendidos_DESDE']."'";
        
        $form_fecha_hasta = "'".$form_values['productosvendidos_HASTA']."'";
        
        //no se incluyen pedidos en estado Pago aceptado, sin stock pagado y similar puesto que aún no se han recogido
        //en $form_estados_pedido_etc, introducimos casi todos los condicionales que varian de un informe a otro, después de los ids de estado de pedido añado un AND que dependiendo de los estados que queremos nos muestra los productos con localización de picking o con localización de reposición. Por ejemplo, si sacamos la lista vendidos en TPV la queremos para hacer reposición de tienda, por lo que usamos la zona de picking y queremos los productos con localización de picking que se han vendido en TPV, pero si queremos los productos que han estado en un pedido que ha pasado por devolución recibida, miramos en la tabla de historial de pedidos que ese estado se haya añadido en esas fechas. Usamos un regex que saque los campos que tengan al menos un caracter, es decir, no vacios ni nulos. En el caso de devoluciones sacamos también sin localización por si esta hubiera sido eliminada.
        SWITCH (Tools::getValue('informe')){	        
	        CASE '0':
	            $form_estados_pedido_etc = "WHERE ord.valid = 1 AND ord.date_add BETWEEN  ".$form_fecha_desde."  AND ".$form_fecha_hasta." AND ord.current_state IN (18,25) AND wpl.location REGEXP '^[a-zA-Z0-9]'";
	            $informe = 'PEDIDOS TIENDA';
	            $orderby = "Localizacion ASC";
	            break;
	        CASE '1':	//sacar productos que estén en pedidos que hayan sido pasados a estado Devolución Recibida (id 47) entre las fechas seleccionadas, sin importar si tiene o no localización (ponerla si la tiene), tampoco importa el estado actual del pedido, pero como al ser devolución podría estar cancelado, no ponemos que ord.valid = 1
	            $form_estados_pedido_etc = "JOIN lafrips_order_history ohi ON ohi.id_order = ord.id_order 
	            	WHERE ohi.date_add BETWEEN  ".$form_fecha_desde."  AND ".$form_fecha_hasta." 
	            	AND ohi.id_order_state = 47 ";
	            $informe = 'DEVOLUCIONES';
	            $orderby = "Localizacion ASC";
	            break;
	        CASE '2': //añadir condición para que salgan solo los productos de categoría 'para preparar' id 2266
	            $form_estados_pedido_etc = "WHERE ord.valid = 1 AND ord.date_add BETWEEN  ".$form_fecha_desde."  AND ".$form_fecha_hasta." AND ord.current_state IN (2,3,4,5,17,18,23,25,26,40,41,42,43) AND ode.product_id IN (SELECT id_product FROM lafrips_category_product WHERE id_category = 2266) ";
	            $informe = 'PRODUCTOS PREPARADOS';
	            $orderby = "Tipo DESC, UnidadesVendidas DESC"; //Para informe de repo Preparados queremos que se ordenen por tipo de producto ypor cantidad vendida para facilitar la fabricación siguiendo un orden
	            break;
			CASE '3': //añadir condición para que salgan solo los productos de pedidos en estado de espera de productos, prepedido, sin stock pagado o reservando, independientemente de la fecha, ya que es current_state, con o sin localización, y quitar los que tengan stock físico superior a 3 que no tienen tanto peligro de rotura de stock. Poniendolo así, los productos que nunca han tenido stock por ser prepedidos totales o estar llegando por primera vez no aparecerán ya que el stock será NULL. También quitamos los productos que salen en el gestor de prepedidos que programó Cima. Para eso utilizo directamente la SELECT que ellos usan en su módulo para sacar los productos que muestra. Cima utiliza una SELECT para productos sin combinaciones y otra para productos con combinaciones, pongo ambas sin tablas innecesarias y cuando tenga tiempo reduzco la consulta.
			//24/04/2020 añadido estado pedido Pedido Diferido id 56
	            $form_estados_pedido_etc = "WHERE ord.valid = 1 AND ord.current_state IN (9,17,23,41,56) AND (SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = ode.product_id AND id_product_attribute = IFNULL( ode.product_attribute_id, 0) AND id_warehouse = 1) < 4
	            	AND ode.product_id NOT IN ( 

						SELECT   p.id_product
						FROM lafrips_product p
						LEFT JOIN lafrips_stock_available psa ON (p.id_product = psa.id_product)
						LEFT JOIN lafrips_stock ps ON (p.id_product = ps.id_product )					    
						LEFT JOIN lafrips_product_attribute_combination pac ON ( psa.id_product_attribute = pac.id_product_attribute )		
						LEFT JOIN cima_gpe gpe ON (p.id_product = gpe.product_id)											    
					    WHERE ( p.id_product IN (SELECT id_product FROM lafrips_category_product WHERE id_category = 121) AND
					    p.id_product IN (SELECT id_product FROM lafrips_stock_available WHERE quantity < 0) OR 
					    ( p.id_product IN (SELECT id_product FROM lafrips_stock_available WHERE quantity < 0) AND 
					     p.id_product NOT IN (SELECT id_product FROM lafrips_product WHERE id_supplier = 0) ) ) AND
					     p.id_product NOT IN (SELECT id_product FROM lafrips_product_attribute)					    
					    GROUP BY psa.id_product, psa.id_product_attribute
					    
	            	)

					AND ode.product_id NOT IN ( 

						SELECT p.id_product
						FROM lafrips_product p
						LEFT JOIN lafrips_stock_available psa ON (p.id_product = psa.id_product)
						LEFT JOIN lafrips_stock ps ON (p.id_product = ps.id_product AND psa.id_product_attribute = ps.id_product_attribute)    
						LEFT JOIN lafrips_product_attribute_combination pac ON ( psa.id_product_attribute = pac.id_product_attribute )    
						LEFT JOIN cima_gpe gpe ON (p.id_product = gpe.product_id AND psa.id_product_attribute = gpe.product_attribute_id)  
					    WHERE ( p.id_product IN (SELECT id_product FROM lafrips_category_product WHERE id_category = 121) AND
					    p.id_product IN (SELECT id_product FROM lafrips_stock_available WHERE quantity < 0) OR 
					    ( p.id_product IN (SELECT id_product FROM lafrips_stock_available WHERE quantity < 0) AND 
					     p.id_product NOT IN (SELECT id_product FROM lafrips_product WHERE id_supplier = 0) ) ) AND
					     p.id_product IN (SELECT id_product FROM lafrips_product_attribute) AND
					     psa.quantity < 0 AND
					     psa.id_product_attribute != 0    
					    GROUP BY psa.id_product, psa.id_product_attribute

	            	)";
	            $informe = 'PRODUCTOS EN ESPERA';
	            $orderby = "Localizacion ASC"; 
	            break;
	        CASE '4': //añadir condiciones para que salgan los productos que hayan sido transferidos de almacén múgica (id warehouse = 4) hacia almacén Online (id warehouse = 1) entre las fechas correspondientes	 
	        //como no me vale la $sql para los otros informes la creo entera abajo           
	            $informe = 'PRODUCTOS TRANSFERIDOS';
	            $form_estados_pedido_etc = '';	
	            $orderby = 'Localizacion ASC';            
				break;
			CASE '5': //añadir condiciones para que salgan los productos contenidos en packs vendidos entre las fechas correspondientes	 
	        //como no me vale la $sql para los otros informes la creo entera abajo           
	            $informe = 'PRODUCTOS EN PACKS';
	            $form_estados_pedido_etc = '';	
	            $orderby = 'Localizacion ASC';            
				break;
			CASE '6': //añadir condiciones para que salgan los productos con stock online y localización de reposición 
			//como no me vale la $sql para los otros informes la creo entera abajo           
				$informe = 'PRODUCTOS CON LOCALIZACIÓN DE REPOSICIÓN';
				$form_estados_pedido_etc = '';	
				$orderby = 'Localizacion ASC';            
				break;
			CASE '7': //añadir condiciones para que salgan los productos descatalogados que tienen stock disponible
			//como no me vale la $sql para los otros informes la creo entera abajo           
				$informe = 'PRODUCTOS DESCATALOGADOS CON STOCK';
				$form_estados_pedido_etc = '';	
				$orderby = 'Localizacion ASC';            
				break;
			CASE '8': //añadir condiciones para que salgan los productos que han cambiado su tipo ABC
			//como no me vale la $sql para los otros informes la creo entera abajo           
				$informe = 'CAMBIO ABC';
				$form_estados_pedido_etc = '';	
				$orderby = '';            
				break;
			CASE '9': //añadir condiciones para que salgan los productos vendidos entre las fechas correspondientes	que sean tipo C y o bien tienen dos localizaciones de repo, o una y est´ñan en la estanteria 2 o más alto, con stock físico 
			//como no me vale la $sql para los otros informes la creo entera abajo           
				$informe = 'REPOSICIÓN PRODUCTOS C';
				$form_estados_pedido_etc = '';	
				$orderby = '';            
				break;
	    }
                        
        $this -> generar_pdf($form_fecha_desde, $form_fecha_hasta, $form_estados_pedido_etc, $orderby);
    }
    

    function generar_pdf($form_fecha_desde, $form_fecha_hasta, $form_estados_pedido_etc, $orderby) {	
    	//a partir del cambio de 21/08/2019 la consulta de $sql que usaba no me sirve para el informe id 4 que saca los productos transferidos, de modo que con un condicional, si el id de informe es 0,1,2 o 3 uso este $sql y si es id 4 uso otro $sql para los transferidos. Posteriormente se han ido añadiendo nuevos informes con su propia consulta
	
    		//sacar tipo de informe e id tipo
     		SWITCH (Tools::getValue('informe')){	        
	        CASE '0':	            
	            $tipoinforme = 'TIENDA';
	            $idtipo = 0;
	            break;
	        CASE '1':	            
	            $tipoinforme = 'DEVOLUCIONES';
	            $idtipo = 1;
	            break;
	        CASE '2': 	            
	            $tipoinforme = 'PRODUCTOS PREPARADOS';
	            $idtipo = 2;
	            break;
	        CASE '3': 	            
	            $tipoinforme = 'PRODUCTOS EN ESPERA';
	            $idtipo = 3;
	            break;
	        CASE '4': 	            
	            $tipoinforme = 'PRODUCTOS TRANSFERIDOS';
	            $idtipo = 4;
				break;
			CASE '5': 	            
	            $tipoinforme = 'PRODUCTOS EN PACKS';
	            $idtipo = 5;
				break;
			CASE '6': 	            
	            $tipoinforme = 'PRODUCTOS CON LOCALIZACION DE REPO';
	            $idtipo = 6;
				break;
			CASE '7': 	            
	            $tipoinforme = 'PRODUCTOS DESCATALOGADOS CON STOCK';
	            $idtipo = 7;
	            break;
			CASE '8': 	            
	            $tipoinforme = 'CAMBIO ABC';
	            $idtipo = 8;
	            break;
			CASE '9': 	            
	            $tipoinforme = 'REPOSICION PRODUCTOS C';
	            $idtipo = 9;
	            break;
	    	}

	    if (($idtipo == 0) || ($idtipo == 1) || ($idtipo == 3)){
	    	//Si el informe es de tienda, devoluciones, preparados o en espera, usamos esta sql
			$sql = "SELECT ode.product_id AS Producto, IFNULL(ode.product_reference, pro.reference) AS Referencia, IFNULL(ode.product_ean13, pro.ean13) AS EAN13, cla.name AS Categoria, ode.product_name AS Nombre, fla.value AS Tipo, SUM( ode.product_quantity ) AS UnidadesVendidas, 		
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = ode.product_id AND id_product_attribute = IFNULL( ode.product_attribute_id, 0) AND id_warehouse = 1) AS Stock_Online,
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = ode.product_id AND id_product_attribute = IFNULL( ode.product_attribute_id, 0) AND id_warehouse = 4) AS Stock_Tienda
			, wpl.location AS Localizacion,  loc.r_location AS Loc_repo, loc.r_cantidad AS StockSeguridad
			FROM lafrips_orders ord
			JOIN lafrips_order_detail ode ON ord.id_order = ode.id_order		
			JOIN lafrips_product pro ON ode.product_id = pro.id_product
			JOIN lafrips_category_lang cla ON pro.id_category_default = cla.id_category		
			JOIN lafrips_warehouse_product_location wpl ON wpl.id_product = ode.product_id AND wpl.id_product_attribute = ode.product_attribute_id
			JOIN lafrips_feature_product fpr ON ode.product_id = fpr.id_product
			JOIN lafrips_feature_value_lang fla ON fpr.id_feature_value = fla.id_feature_value
			LEFT JOIN lafrips_localizaciones loc ON loc.id_product = ode.product_id AND loc.id_product_attribute = ode.product_attribute_id
			".$form_estados_pedido_etc." 
			AND cla.id_lang = 1 
			AND wpl.id_warehouse =1	
			AND fla.id_lang = 1
	        AND fpr.id_feature = 8			
			GROUP BY ode.product_id ORDER BY ".$orderby."";
		}elseif ($idtipo == 2){
			//Si el informe es de productos preparados, usamos esta sql
			$sql = "SELECT ode.product_id AS Producto, IFNULL(ode.product_reference, pro.reference) AS Referencia, IFNULL(ode.product_ean13, pro.ean13) AS EAN13, cla.name AS Categoria, ode.product_name AS Nombre, fla.value AS Tipo, SUM( ode.product_quantity ) AS UnidadesVendidas, 		
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = ode.product_id AND id_product_attribute = IFNULL( ode.product_attribute_id, 0) AND id_warehouse = 1) AS Stock_Online,			
			wpl.location AS Localizacion,  loc.r_location AS Loc_repo, 
			CASE                                
				WHEN DATEDIFF(NOW(), pro.date_add) <= 60 THEN 'N'
				WHEN con.consumo IS NULL THEN 1
			ELSE IF((con.consumo < 0.5), 1, ROUND(con.consumo, 0))
			END AS consumo
			FROM lafrips_orders ord
			JOIN lafrips_order_detail ode ON ord.id_order = ode.id_order		
			JOIN lafrips_product pro ON ode.product_id = pro.id_product
			JOIN lafrips_category_lang cla ON pro.id_category_default = cla.id_category		
			JOIN lafrips_warehouse_product_location wpl ON wpl.id_product = ode.product_id AND wpl.id_product_attribute = ode.product_attribute_id
			JOIN lafrips_feature_product fpr ON ode.product_id = fpr.id_product
			JOIN lafrips_feature_value_lang fla ON fpr.id_feature_value = fla.id_feature_value
			LEFT JOIN lafrips_localizaciones loc ON loc.id_product = ode.product_id AND loc.id_product_attribute = ode.product_attribute_id
			LEFT JOIN lafrips_consumos con ON con.id_product = ode.product_id AND con.id_product_attribute = ode.product_attribute_id
			".$form_estados_pedido_etc." 
			AND cla.id_lang = 1 
			AND wpl.id_warehouse =1	
			AND fla.id_lang = 1
	        AND fpr.id_feature = 8			
			GROUP BY ode.product_id ORDER BY ".$orderby."";
		}elseif ($idtipo == 4){
			//Si el informe es de transferidos usamos esta otra sql
			$sql = "SELECT DISTINCT sto.id_product AS Producto, pla.name AS Nombre, sto.reference AS Referencia, sto.ean13 AS EAN13, wpl.location AS Localizacion,
				(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = sto.id_product AND id_product_attribute = IFNULL( sto.id_product_attribute, 0) AND id_warehouse = 1)  AS Stock_Online,
				(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = sto.id_product AND id_product_attribute = IFNULL( sto.id_product_attribute, 0) AND id_warehouse = 4) AS Stock_Tienda
				FROM lafrips_stock_mvt smv
				LEFT JOIN lafrips_stock sto ON smv.id_stock = sto.id_stock
				LEFT JOIN lafrips_product_lang pla ON sto.id_product = pla.id_product
				LEFT JOIN lafrips_employee emp ON emp.id_employee = smv.id_employee
				LEFT JOIN lafrips_warehouse_product_location wpl ON wpl.id_product = sto.id_product AND wpl.id_product_attribute = sto.id_product_attribute
				WHERE sto.id_warehouse IN (4)
				AND pla.id_lang = 1
				AND wpl.id_warehouse = 1
				AND smv.id_employee NOT IN (22)
				AND smv.id_stock_mvt_reason IN (6)
				AND smv.date_add BETWEEN  ".$form_fecha_desde."  AND ".$form_fecha_hasta."
				ORDER BY ".$orderby."";
		}elseif ($idtipo == 5){
			//Si el informe es de productos de packs usamos esta otra sql
			$sql = "SELECT pac.id_product_item AS Producto, pac.id_product_attribute_item AS Atributo_Producto, pro.reference AS Referencia, 
			pro.ean13 AS EAN13, cla.name AS Categoria, pla.name AS Nombre, fla.value AS Tipo,  		
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = pac.id_product_item AND id_product_attribute = IFNULL( pac.id_product_attribute_item, 0) AND id_warehouse = 1) AS Stock_Online,
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = pac.id_product_item AND id_product_attribute = IFNULL( pac.id_product_attribute_item, 0) AND id_warehouse = 4) AS Stock_Tienda
			, wpl.location AS Localizacion,  loc.r_location AS Loc_repo, loc.r_cantidad AS StockSeguridad
			FROM lafrips_pack pac
			JOIN lafrips_product pro ON pro.id_product = pac.id_product_item
			JOIN lafrips_product_lang pla ON pla.id_product = pac.id_product_item AND pla.id_lang = 1
			JOIN lafrips_category_lang cla ON pro.id_category_default = cla.id_category AND cla.id_lang = 1 		
			JOIN lafrips_warehouse_product_location wpl ON wpl.id_product = pac.id_product_item AND wpl.id_product_attribute = pac.id_product_attribute_item
			JOIN lafrips_feature_product fpr ON pac.id_product_item = fpr.id_product AND fpr.id_feature = 8
			JOIN lafrips_feature_value_lang fla ON fpr.id_feature_value = fla.id_feature_value AND fla.id_lang = 1
			LEFT JOIN lafrips_localizaciones loc ON loc.id_product = pac.id_product_item AND loc.id_product_attribute = pac.id_product_attribute_item
			WHERE id_product_pack IN
			(SELECT DISTINCT ode.product_id 
			FROM lafrips_orders ord
			JOIN lafrips_order_detail ode ON ord.id_order = ode.id_order
			JOIN lafrips_product pro ON ode.product_id = pro.id_product
			WHERE ord.valid = 1 
			AND pro.cache_is_pack = 1 
			AND ord.date_add BETWEEN  ".$form_fecha_desde."  AND ".$form_fecha_hasta."
			AND ord.current_state IN (2,3,4,5,17,18,23,25,26,29,40,41,42,43,46,47,55,56,57)) 
			GROUP BY pac.id_product_item ORDER BY ".$orderby;
		}elseif ($idtipo == 6){
			//Si el informe es de productos con stock online y localización de reposición usamos esta otra sql
			$sql = "SELECT DISTINCT loc.id_product AS Producto, pla.name AS Nombre, IFNULL(pat.reference, pro.reference) AS Referencia, 
			IFNULL(pat.ean13, pro.ean13) AS EAN13, wpl.location AS Localizacion,
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = loc.id_product AND id_product_attribute = IFNULL( loc.id_product_attribute, 0) AND id_warehouse = 1)  AS Stock_Online,
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = loc.id_product AND id_product_attribute = IFNULL( loc.id_product_attribute, 0) AND id_warehouse = 4) AS Stock_Tienda,  
			loc.r_location AS Loc_repo
			FROM lafrips_localizaciones loc
			LEFT JOIN lafrips_stock sto ON loc.id_product = sto.id_product AND loc.id_product_attribute = sto.id_product_attribute
			LEFT JOIN lafrips_product pro ON loc.id_product = pro.id_product 
			LEFT JOIN lafrips_product_attribute pat ON pat.id_product = loc.id_product AND pat.id_product_attribute = loc.id_product_attribute
			LEFT JOIN lafrips_product_lang pla ON loc.id_product = pla.id_product AND pla.id_lang = 1
			LEFT JOIN lafrips_warehouse_product_location wpl ON wpl.id_product = loc.id_product AND wpl.id_product_attribute = loc.id_product_attribute
			WHERE sto.id_warehouse = 1
			AND wpl.id_warehouse = 1
			AND (SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = loc.id_product AND id_product_attribute = IFNULL( loc.id_product_attribute, 0) AND id_warehouse = 1) > 0
			AND loc.r_location != ''
			AND loc.r_location IS NOT NULL
			ORDER BY ".$orderby;
		}elseif ($idtipo == 7){
			//Si el informe es de productos descatalogados con stock disponible usamos esta otra sql
			$sql = "SELECT ava.id_product AS Producto, ava.id_product_attribute AS id_product_attribute, IFNULL(pat.reference, pro.reference) AS Referencia,
			pla.name AS Nombre, ava.quantity AS Cantidad ,  
			IFNULL(pat.ean13, pro.ean13) AS EAN13, wpl.location AS Localizacion,
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = loc.id_product AND id_product_attribute = IFNULL( loc.id_product_attribute, 0) AND id_warehouse = 1)  AS Stock_Online,
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = loc.id_product AND id_product_attribute = IFNULL( loc.id_product_attribute, 0) AND id_warehouse = 4) AS Stock_Tienda,  
			loc.r_location AS Loc_repo
			FROM lafrips_stock_available ava
			JOIN lafrips_product pro ON pro.id_product = ava.id_product
			LEFT JOIN lafrips_product_attribute pat ON pat.id_product = ava.id_product AND pat.id_product_attribute = ava.id_product_attribute
			JOIN lafrips_product_lang pla ON pro.id_product = pla.id_product AND pla.id_lang = 1
			LEFT JOIN lafrips_warehouse_product_location wpl ON wpl.id_product = ava.id_product AND wpl.id_product_attribute = ava.id_product_attribute
			LEFT JOIN lafrips_localizaciones loc ON loc.id_product = ava.id_product AND loc.id_product_attribute = ava.id_product_attribute
			WHERE pro.id_category_default = 89
			AND wpl.id_warehouse = 1	
			AND ava.quantity > 0
			ORDER BY ".$orderby;
		}elseif ($idtipo == 8){
			//Si el informe es de productos con cambio de tipo ABC usamos esta otra sql
			$sql = "SELECT col.id_product AS Producto, col.id_product_attribute, col.abc AS abc, col.abc_previo AS abc_previo, pla.name AS Nombre,
			IFNULL(pat.reference, pro.reference) AS Referencia, IFNULL(pat.ean13, pro.ean13) AS EAN13, wpl.location AS Localizacion,
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = col.id_product AND id_product_attribute = IFNULL( col.id_product_attribute, 0) AND id_warehouse = 1)  AS Stock_Online,
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = col.id_product AND id_product_attribute = IFNULL( col.id_product_attribute, 0) AND id_warehouse = 4) AS Stock_Tienda,   
			loc.r_location AS Loc_repo, con.consumo AS consumo 
			FROM lafrips_consumos_log col
			JOIN lafrips_product pro ON pro.id_product = col.id_product
			LEFT JOIN lafrips_consumos con ON con.id_product = col.id_product AND con.id_product_attribute = col.id_product_attribute
			LEFT JOIN lafrips_product_lang pla ON col.id_product = pla.id_product AND pla.id_lang = 1
			LEFT JOIN lafrips_product_attribute pat ON pat.id_product = col.id_product AND pat.id_product_attribute = col.id_product_attribute
			LEFT JOIN lafrips_localizaciones loc ON loc.id_product = col.id_product AND loc.id_product_attribute = col.id_product_attribute
			LEFT JOIN lafrips_warehouse_product_location wpl ON wpl.id_product = col.id_product AND wpl.id_product_attribute = col.id_product_attribute
				AND wpl.id_warehouse = 1
			WHERE col.date_add BETWEEN ".$form_fecha_desde." AND ".$form_fecha_hasta."
			AND col.abc != col.abc_previo
			AND col.abc_previo != '-'
			AND (SELECT SUM(physical_quantity) FROM lafrips_stock 
				WHERE id_product = col.id_product AND id_product_attribute = IFNULL( col.id_product_attribute, 0) AND id_warehouse = 1) > 0
			ORDER BY col.abc_previo, wpl.location ASC";
		}elseif ($idtipo == 9){
			//Si el informe es de reposición productos Cs, usamos esta sql
			//08/10/2021 añadidas dos zonas más de palets para repo, 1501 y 1502
			$sql = "SELECT ode.product_id AS Producto, ode.product_reference AS Referencia, ode.product_ean13 AS EAN13, cla.name AS Categoria, ode.product_name AS Nombre, fla.value AS Tipo, SUM( ode.product_quantity ) AS UnidadesVendidas, 		
			(SELECT SUM(physical_quantity) FROM lafrips_stock WHERE id_product = ode.product_id AND id_product_attribute = ode.product_attribute_id AND id_warehouse = 1) AS Stock_Online,			
			wpl.location AS Localizacion,  loc.r_location AS Loc_repo, 
			CASE                                
				WHEN DATEDIFF(NOW(), pro.date_add) <=  (SELECT value FROM lafrips_configuration                                                                                      											WHERE name = 'CLASIFICACIONABC_NOVEDAD')  THEN 'N'
				WHEN con.consumo IS NULL THEN 1
			ELSE IF((con.consumo < 0.5), 1, ROUND(con.consumo, 0))
			END AS consumo
			FROM lafrips_orders ord
			JOIN lafrips_order_detail ode ON ord.id_order = ode.id_order		
			JOIN lafrips_product pro ON ode.product_id = pro.id_product
			JOIN lafrips_category_lang cla ON pro.id_category_default = cla.id_category		
			LEFT JOIN lafrips_warehouse_product_location wpl ON wpl.id_product = ode.product_id AND wpl.id_product_attribute = ode.product_attribute_id
			LEFT JOIN lafrips_feature_product fpr ON ode.product_id = fpr.id_product
			LEFT JOIN lafrips_feature_value_lang fla ON fpr.id_feature_value = fla.id_feature_value
			LEFT JOIN lafrips_localizaciones loc ON loc.id_product = ode.product_id AND loc.id_product_attribute = ode.product_attribute_id
			LEFT JOIN lafrips_consumos con ON con.id_product = ode.product_id AND con.id_product_attribute = ode.product_attribute_id
			WHERE ord.valid = 1 
			AND ord.date_add BETWEEN ".$form_fecha_desde." AND ".$form_fecha_hasta."
			AND ord.current_state IN (2,3,4,9,17,20,23,26,27,28,29,34,40,41,42,43,55,56,59,64,65)
			#AND ode.product_id IN (SELECT id_product FROM lafrips_category_product WHERE id_category = 2266) 
			AND (SELECT SUM(physical_quantity) FROM lafrips_stock 
				WHERE id_product = ode.product_id AND id_product_attribute = ode.product_attribute_id AND id_warehouse = 1) > 0
			#loc de repo con / o cuya localización sea la altura tiene que valer 2 o más, es el tercero por la izquierda. O que empiece por 15  
			AND (loc.r_location REGEXP '/' OR loc.r_location REGEXP '^.+[23456789]..$'  OR loc.r_location REGEXP '^150' ) #match si el tercer nº por la derch es 2 o más
			AND ( #sacar solo los C - No novedad, consumo de menos de 0.5, o no están en tabla de consumo
			CASE                                
				WHEN DATEDIFF(NOW(), pro.date_add) <= (SELECT value FROM lafrips_configuration                                                                                      											WHERE name = 'CLASIFICACIONABC_NOVEDAD') THEN 0
				WHEN con.consumo IS NULL THEN 1
			ELSE IF((con.consumo < 0.5), 1, 0)
			END
			)
			AND cla.id_lang = 1 
			AND wpl.id_warehouse =1	
			AND fla.id_lang = 1
			AND fpr.id_feature = 8	
			AND ord.id_customer NOT IN (SELECT id_customer FROM lafrips_customer_group WHERE id_group = 10)  #evitamos los clientes del grupo CAJAS		
			GROUP BY ode.product_id, ode.product_attribute_id ORDER BY loc.r_location ASC, UnidadesVendidas DESC";
		}
			    
		$resultados = Db::getInstance()->executeS($sql);
     	
	


     	//Si el check de 'Guardar fecha hasta' estaba marcado, insertar en base de datos la info del informe. Con JQUERY ponemos valor 1 o 0 al hidden oculto_guarda según esté o no marcado:
     	if(Tools::getValue('oculto_guarda')){
     		$sql = "INSERT INTO frik_informes_reposicion (id_tipo, tipo, fecha_desde, fecha_hasta, creado) VALUES (".$idtipo.",'".$tipoinforme."', ".$form_fecha_desde.",".$form_fecha_hasta.", NOW());";
			Db::getInstance()->ExecuteS($sql);
     	}
	    
		$pdf = new MY_PDF_REPO(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		// MOD Cima Digital -  Ponemos OB_CLEAN() para que genere el PDF 
		ob_clean();
		// set header and footer fonts
		$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
		//$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
		$pdf->SetHeaderData(PDF_HEADER_TITLE, PDF_HEADER_STRING);/*****************/
		
		// set default monospaced font
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
		
		// set margins
		$pdf->SetMargins(0, 15, 5);
		$pdf->SetHeaderMargin(0); 
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
		
		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
		
		// set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
		
		// set some language-dependent strings (optional)
		if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
		    require_once(dirname(__FILE__).'/lang/eng.php');
		    $pdf->setLanguageArray($l);
		}
		
		// ---------------------------------------------------------
		
		// set default font subsetting mode
		$pdf->setFontSubsetting(false);
		
		// Set font
		// dejavusans is a UTF-8 Unicode font, if you only need to
		// print standard ASCII chars, you can use core fonts like
		// helvetica or times to reduce file size.
		$pdf->SetFont('helvetica', '', 10, '', true);
		
		// Add a page
		// This method has several options, check the source code documentation for more information.
		$pdf->AddPage();
		
		// set text shadow effect
		$pdf->setTextShadow(array('enabled'=>true, 'depth_w'=>0.2, 'depth_h'=>0.2, 'color'=>array(196,196,196), 'opacity'=>1, 'blend_mode'=>'Normal'));
		
		
		$html = '';
		
					
			$html = $html.'
		<table>
			 <table style="width:100%">
		  <tr>
		  	<th width="16%"><span style="font-size: 40px; text-decoration: underline">Imagen</span></th>';


		if (Tools::getValue('informe') == 0){ //Si el informe es reposición de tienda:
			$html = $html.'<th width="6%"><span style="font-size: 40px; text-decoration: underline">ID</span></th>
		    <th width="13%"><span style="font-size: 40px; text-decoration: underline">Referencia</span></th>
			<th width="14%"><span style="font-size: 40px; text-decoration: underline">EAN</span></th>
			<th width="14%"><span style="font-size: 40px; text-decoration: underline">Nombre</span></th>
		    <th width="13%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Picking</span></th>
		    <th width="4%"><span style="font-size: 40px; text-decoration: underline">Ud</span></th>
		    <th width="7%"><span style="font-size: 35px; text-decoration: underline">Online</span></th>
		    <th width="8%"><span style="font-size: 35px; text-decoration: underline">Tienda</span></th>';

		}

		if (Tools::getValue('informe') == 1){ //Si el informe es productos de devoluciones
			$html = $html.'<th width="6%"><span style="font-size: 40px; text-decoration: underline">ID</span></th>
		    <th width="13%"><span style="font-size: 40px; text-decoration: underline">Referencia</span></th><th width="14%"><span style="font-size: 40px; text-decoration: underline">EAN</span></th>
			<th width="14%"><span style="font-size: 40px; text-decoration: underline">Nombre</span></th>
		    <th width="13%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Picking</span></th>
		    <th width="4%"><span style="font-size: 40px; text-decoration: underline">Ud</span></th>
		    <th width="7%"><span style="font-size: 35px; text-decoration: underline">Online</span></th>
		    <th width="8%"><span style="font-size: 35px; text-decoration: underline">Tienda</span></th>';

		}

		if (Tools::getValue('informe') == 2){ //Si el informe es reposición de productos preparados
			$html = $html.'<th width="6%"><span style="font-size: 40px; text-decoration: underline">ID</span></th>
		    <th width="13%"><span style="font-size: 40px; text-decoration: underline">Referencia</span></th>    
		    <th width="19%"><span style="font-size: 40px; text-decoration: underline">Nombre</span></th>
		    <th width="14%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Picking</span></th>
		    <th width="4%"><span style="font-size: 40px; text-decoration: underline">Ud</span></th>
		    <th width="7%"><span style="font-size: 35px; text-decoration: underline">Stock</span></th>		    
		    <th width="14%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Reposición</span></th>
		    <th width="10%"><span style="font-size: 40px; text-decoration: underline">Consumo Medio</span></th>';

		}

		if (Tools::getValue('informe') == 3){ //Si el informe es productos en espera
			$html = $html.'<th width="6%"><span style="font-size: 40px; text-decoration: underline">ID</span></th>
		    <th width="13%"><span style="font-size: 40px; text-decoration: underline">Referencia</span></th>
			<th width="14%"><span style="font-size: 40px; text-decoration: underline">EAN</span></th>
			<th width="14%"><span style="font-size: 40px; text-decoration: underline">Nombre</span></th>
		    <th width="13%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Picking</span></th>
		    <th width="4%"><span style="font-size: 40px; text-decoration: underline">Ud</span></th>
		    <th width="7%"><span style="font-size: 35px; text-decoration: underline">Online</span></th>
		    <th width="8%"><span style="font-size: 35px; text-decoration: underline">Tienda</span></th>';

		}

		if (Tools::getValue('informe') == 4){ //Si el informe es productos transferidos
			$html = $html.'<th width="6%"><span style="font-size: 40px; text-decoration: underline">ID</span></th>
		    <th width="13%"><span style="font-size: 40px; text-decoration: underline">Referencia</span></th>
			<th width="15%"><span style="font-size: 40px; text-decoration: underline">EAN</span></th>
			<th width="15%"><span style="font-size: 40px; text-decoration: underline">Nombre</span></th>
		    <th width="14%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Picking</span></th>
		    <th width="8%"><span style="font-size: 35px; text-decoration: underline">Online</span></th>
		    <th width="8%"><span style="font-size: 35px; text-decoration: underline">Tienda</span></th>';

		}

		if (Tools::getValue('informe') == 5){ //Si el informe es productos en packs
			$html = $html.'<th width="6%"><span style="font-size: 40px; text-decoration: underline">ID</span></th>
		    <th width="13%"><span style="font-size: 40px; text-decoration: underline">Referencia</span></th>
			<th width="14%"><span style="font-size: 40px; text-decoration: underline">EAN</span></th>
			<th width="14%"><span style="font-size: 40px; text-decoration: underline">Nombre</span></th>
		    <th width="13%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Picking</span></th>
		    <th width="7%"><span style="font-size: 35px; text-decoration: underline">Online</span></th>
			<th width="8%"><span style="font-size: 35px; text-decoration: underline">Tienda</span></th>
			<th width="10%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Repo</span></th>';

		}

		if (Tools::getValue('informe') == 6){ //Si el informe es productos con stock online y localización de reposición
			$html = $html.'<th width="6%"><span style="font-size: 40px; text-decoration: underline">ID</span></th>
		    <th width="13%"><span style="font-size: 40px; text-decoration: underline">Referencia</span></th>
			<th width="14%"><span style="font-size: 40px; text-decoration: underline">EAN</span></th>
			<th width="14%"><span style="font-size: 40px; text-decoration: underline">Nombre</span></th>
		    <th width="13%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Picking</span></th>
		    <th width="7%"><span style="font-size: 35px; text-decoration: underline">Online</span></th>
			<th width="8%"><span style="font-size: 35px; text-decoration: underline">Tienda</span></th>
			<th width="10%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Repo</span></th>';

		}

		if (Tools::getValue('informe') == 7){ //Si el informe es productos descatalogados con stock
			$html = $html.'<th width="6%"><span style="font-size: 40px; text-decoration: underline">ID</span></th>
		    <th width="13%"><span style="font-size: 40px; text-decoration: underline">Referencia</span></th>
			<th width="14%"><span style="font-size: 40px; text-decoration: underline">EAN</span></th>
			<th width="12%"><span style="font-size: 40px; text-decoration: underline">Nombre</span></th>
			<th width="3%"><span style="font-size: 40px; text-decoration: underline">Ud</span></th>
		    <th width="13%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Picking</span></th>
		    <th width="7%"><span style="font-size: 35px; text-decoration: underline">Online</span></th>
			<th width="8%"><span style="font-size: 35px; text-decoration: underline">Tienda</span></th>
			<th width="10%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Repo</span></th>';

		}

		if (Tools::getValue('informe') == 8){ //Si el informe es productos ABC cambiados de tipo
			$html = $html.'
		    <th width="13%"><span style="font-size: 40px; text-decoration: underline">Referencia</span></th>			
			<th width="14%"><span style="font-size: 40px; text-decoration: underline">Nombre</span></th>
			<th width="6%"><span style="font-size: 30px; text-decoration: underline">Desde</span></th>
			<th width="6%"><span style="font-size: 30px; text-decoration: underline">Hasta</span></th>
			<th width="10%"><span style="font-size: 40px; text-decoration: underline">Consumo</span></th>
		    <th width="11%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Picking</span></th>
		    <th width="7%"><span style="font-size: 35px; text-decoration: underline">Online</span></th>
			<th width="8%"><span style="font-size: 35px; text-decoration: underline">Tienda</span></th>
			<th width="11%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Repo</span></th>';

		}

		if (Tools::getValue('informe') == 9){ //Si el informe es reposición de productos C
			$html = $html.'<th width="6%"><span style="font-size: 40px; text-decoration: underline">ID</span></th>
		    <th width="13%"><span style="font-size: 40px; text-decoration: underline">Referencia</span></th>    
		    <th width="19%"><span style="font-size: 40px; text-decoration: underline">Nombre</span></th>
		    <th width="14%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Picking</span></th>
		    <th width="4%"><span style="font-size: 40px; text-decoration: underline">Ud</span></th>
		    <th width="7%"><span style="font-size: 35px; text-decoration: underline">Stock</span></th>		    
		    <th width="14%" style="text-align:right;"><span style="font-size: 40px; text-decoration: underline">Reposición</span></th>
		    <th width="10%"><span style="font-size: 40px; text-decoration: underline">Consumo Medio</span></th>';

		}
		    
		$html = $html.'</tr>';
		  
		  $linea_producto = 0; //para sacar la tabla con sombreado por líneas
		  $ultimo_id = '';
		  $contador = 0;

		  foreach ($resultados as $row) {
		  		//sumamos contador para sacar el total de productos individuales en la lista para su inserción en la tabla de historial
		  		$contador++;
		  		// MOD: Añadimos el sql para obtener los datos de los productos en cada linea / quito WHERE p.active = 1 para que saque imagen de productos desactivados
		  		$sql_imagen = "SELECT 
		  			CONCAT('https://lafrikileria.com/', im.id_image, '-small_default/', p.id_product,'.jpg') AS URL_IMAGEN,
		  			im.id_image AS 'existe_imagen'
					FROM lafrips_product p
					INNER JOIN lafrips_image im ON im.id_product = p.id_product
					WHERE im.cover = 1 
					AND p.id_product = " . $row['Producto'];
		  		$resultado_imagen = Db::getInstance()->executeS($sql_imagen);

		  		// Revisa el campo del id de imagen y comprueba que exista, sino inserta el logo                
                if (empty($resultado_imagen[0]['existe_imagen'])) {
                    $resultado_imagen[0]['URL_IMAGEN'] = 'https://lafrikileria.com/img/logo_producto_medium_default.jpg';
                }

		  		
		  		
		  		// Revisa la imagen y comprueba que exista, sino inserta el logo				
				/*if (!empty(@getimagesize($resultado_imagen[0]['URL_IMAGEN']))) {
					$resultado_imagen[0]['URL_IMAGEN'] = 'https://lafrikileria.com/img/lafrikileria-logo-1437124132.jpg';
		  		}*/

		  		//Si no tiene stock en tienda y nunca lo tuvo, Stock_tienda estará vacio, le ponemos 0
		  		if (!$row['Stock_Tienda']){
		  			$row['Stock_Tienda'] = 0;
		  		}

				//si sale un producto que aún no ha tenido stock ¿?
				if (!$row['Stock_Online']){
					$row['Stock_Online'] = 0;
				}
		  				  	
	  			$linea_producto = $linea_producto + 1;
				$ultimo_id = $row['Producto'];
    			$html .= '<tr'; 


				if ($linea_producto % 2 != 0) {
					$html .= ' bgcolor="#EEEEEE" ';
				}

    			$html .= ' style="display: block;">
    					<td><img style="height: 100px;" src="'.$resultado_imagen[0]['URL_IMAGEN'].'" /></td>';

    			//Dependiendo del tipo de informe creamos la tabla con unos datos u otros:
    			if ((Tools::getValue('informe') == 0) || (Tools::getValue('informe') == 1) || (Tools::getValue('informe') == 3)){
    				$html = $html.'
						<td>'.$row['Producto'].'</td>
						<td>'.$row['Referencia'].'</td>
						<td>'.$row['EAN13'].'</td>
	    				<td>'.$row['Nombre'].'</td>
						<td style="text-align:right;">'.$row['Localizacion'].'</td>
						<td style="text-align:right;">'.$row['UnidadesVendidas'].'</td>
						<td style="text-align:center;">'.$row['Stock_Online'].'</td>
						<td style="text-align:center;">'.$row['Stock_Tienda'].'</td>';

    			}else if (Tools::getValue('informe') == 2){//informe de preparados
    				$html = $html.'
						<td>'.$row['Producto'].'</td>
						<td>'.$row['Referencia'].'</td> 		
    					<td>'.$row['Nombre'].'</td>
    					<td style="text-align:center;">'.$row['Localizacion'].'</td>
    					<td style="text-align:center;">'.$row['UnidadesVendidas'].'</td>
    					<td style="text-align:center;">'.$row['Stock_Online'].'</td>    					
    					<td style="text-align:center;">'.$row['Loc_repo'].'</td>
						<td style="text-align:center;">'.$row['consumo'].'</td>'; 

    			}else if (Tools::getValue('informe') == 4){//productos transferidos
    				$html = $html.'
						<td>'.$row['Producto'].'</td>
    					<td>'.$row['Referencia'].'</td>
						<td>'.$row['EAN13'].'</td>
	    				<td>'.$row['Nombre'].'</td>
						<td style="text-align:right;">'.$row['Localizacion'].'</td>						
						<td style="text-align:center;">'.$row['Stock_Online'].'</td>
						<td style="text-align:center;">'.$row['Stock_Tienda'].'</td>';

    			}else if (Tools::getValue('informe') == 5){//informe productos en packs
    				$html = $html.'
						<td>'.$row['Producto'].'</td>
    					<td>'.$row['Referencia'].'</td>
						<td>'.$row['EAN13'].'</td> 		
    					<td>'.$row['Nombre'].'</td>
    					<td style="text-align:right;">'.$row['Localizacion'].'</td>    					
    					<td style="text-align:center;">'.$row['Stock_Online'].'</td>
    					<td style="text-align:center;">'.$row['Stock_Tienda'].'</td>
    					<td style="text-align:right;">'.$row['Loc_repo'].'</td>'; 

    			}else if (Tools::getValue('informe') == 6){//informe productos con localización reposición
    				$html = $html.'
						<td>'.$row['Producto'].'</td>
    					<td>'.$row['Referencia'].'</td>
						<td>'.$row['EAN13'].'</td> 		
    					<td>'.$row['Nombre'].'</td>
    					<td style="text-align:right;">'.$row['Localizacion'].'</td>    					
    					<td style="text-align:center;">'.$row['Stock_Online'].'</td>
    					<td style="text-align:center;">'.$row['Stock_Tienda'].'</td>
    					<td style="text-align:right;">'.$row['Loc_repo'].'</td>'; 

    			}else if (Tools::getValue('informe') == 7){//informe productos descatalogados con stock
    				$html = $html.'
						<td>'.$row['Producto'].'</td>
						<td>'.$row['Referencia'].'</td>
						<td>'.$row['EAN13'].'</td> 		
						<td>'.$row['Nombre'].'</td>
						<td style="text-align:right;">'.$row['Cantidad'].'</td>
    					<td style="text-align:right;">'.$row['Localizacion'].'</td>    					
    					<td style="text-align:center;">'.$row['Stock_Online'].'</td>
    					<td style="text-align:center;">'.$row['Stock_Tienda'].'</td>
    					<td style="text-align:right;">'.$row['Loc_repo'].'</td>'; 

    			} else if (Tools::getValue('informe') == 8) { //informe productos ABC desplazados
					// redondeamos consumo
					$consumo = $row['consumo'];
					if (!$consumo) {
						$consumo = 0;
					} else {
						if ($consumo >= 7) {							
							$consumo = round($consumo);
						} else if ($consumo <= 0.5) {							
							$consumo = 1; //dejamos 1 por defecto cuando sea menos
						} else {							
							$consumo = round($consumo);
						}
					}
						  
    				$html = $html.'						
						<td>'.$row['Referencia'].'</td>						 		
						<td>'.$row['Nombre'].'</td>
						<td style="text-align:center;">'.$row['abc_previo'].'</td>
						<td style="text-align:center;">'.$row['abc'].'</td>
						<td style="text-align:center;">'.$consumo.'</td>
    					<td style="text-align:right;">'.$row['Localizacion'].'</td>    					
    					<td style="text-align:center;">'.$row['Stock_Online'].'</td>
    					<td style="text-align:center;">'.$row['Stock_Tienda'].'</td>
    					<td style="text-align:right;">'.$row['Loc_repo'].'</td>'; 

    			} else if (Tools::getValue('informe') == 9){//informe de productos tipo C
    				$html = $html.'
						<td>'.$row['Producto'].'</td>
						<td>'.$row['Referencia'].'</td> 		
    					<td>'.$row['Nombre'].'</td>
    					<td style="text-align:center;">'.$row['Localizacion'].'</td>
    					<td style="text-align:center;">'.$row['UnidadesVendidas'].'</td>
    					<td style="text-align:center;">'.$row['Stock_Online'].'</td>    					
    					<td style="text-align:center;">'.$row['Loc_repo'].'</td>
						<td style="text-align:center;">'.$row['consumo'].'</td>'; 
    			}
		       	
    			$html = $html.'</tr>';	
		  		
		  	}

		$html .= '</table> 
		</table>'; 	
		
		// Print text using writeHTMLCell()
		$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
		
		// ---------------------------------------------------------
		
		// Close and output PDF document
		// This method has several options, check the source code documentation for more information.
		$pdf->Output('Informe - '.$tipoinforme.' - '.date('d').'-'.date('m').'-'.date('Y').'_'.date('H').''.date('i').'.pdf', 'D');
		
		//============================================================+
		// END OF FILE
		//============================================================+
		 
    }


    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        //$this->context->controller->addJS($this->_path.'/views/js/front.js');
        //$this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }
}