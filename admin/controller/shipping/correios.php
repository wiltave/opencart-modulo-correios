<?php
class ControllerShippingCorreios extends Controller {
	private $error = array(); 
	
	public function index() {   
		$this->load->language('shipping/correios');
		
		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {
			$this->model_setting_setting->editSetting('correios', $this->request->post);		
			
			$this->session->data['success'] = $this->language->get('text_success');
			
			$this->redirect($this->url->link('extension/shipping', 'token=' . $this->session->data['token'], 'SSL'));
		}
		
		$this->data['heading_title'] = $this->language->get('heading_title');
		
		$this->data['text_enabled'] = $this->language->get('text_enabled');
		$this->data['text_disabled'] = $this->language->get('text_disabled');
		$this->data['text_all_zones'] = $this->language->get('text_all_zones');
		$this->data['text_none'] = $this->language->get('text_none');
		$this->data['text_sim'] = $this->language->get('text_sim');
		$this->data['text_nao'] = $this->language->get('text_nao');
		$this->data['text_sedex'] = $this->language->get('text_sedex');
		$this->data['text_sedex_cobrar'] = $this->language->get('text_sedex_cobrar');
		$this->data['text_pac'] = $this->language->get('text_pac');
		$this->data['text_sedex_10'] = $this->language->get('text_sedex_10');
		$this->data['text_esedex'] = $this->language->get('text_esedex');
		$this->data['entry_cost'] = $this->language->get('entry_cost');
		$this->data['entry_tax'] = $this->language->get('entry_tax');
		$this->data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$this->data['entry_status'] = $this->language->get('entry_status');
		$this->data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$this->data['entry_servicos'] = $this->language->get('entry_servicos');
		$this->data['entry_prazo_adicional'] = $this->language->get('entry_prazo_adicional');
		$this->data['entry_esedex'] = $this->language->get('entry_esedex');
		$this->data['entry_esedex_codigo'] = $this->language->get('entry_esedex_codigo');
		$this->data['entry_esedex_senha'] = $this->language->get('entry_esedex_senha');
		
		$this->data['button_save'] = $this->language->get('button_save');
		$this->data['button_cancel'] = $this->language->get('button_cancel');
		
		$this->data['tab_general'] = $this->language->get('tab_general');
		
		$this->data['entry_postcode']= $this->language->get('entry_postcode');
		$this->data['entry_mao_propria']= $this->language->get('entry_mao_propria');
		$this->data['entry_aviso_recebimento']= $this->language->get('entry_aviso_recebimento');
		$this->data['entry_declarar_valor']= $this->language->get('entry_declarar_valor');
		$this->data['entry_adicional']= $this->language->get('entry_adicional');
		
		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}
		if (isset($this->error['postcode'])) {
			$this->data['error_postcode'] = $this->error['postcode'];
		} else {
			$this->data['error_postcode'] = '';
		}
		if (isset($this->error['servico'])) {
			$this->data['error_servico'] = $this->error['servico'];
		} else {
			$this->data['error_servico'] = '';
		}		
		
		$this->data['breadcrumbs'] = array();
   		
   		$this->data['breadcrumbs'][] = array(
       		'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
       		'text'      => $this->language->get('text_home'),
      		'separator' => FALSE
   		);
   		$this->data['breadcrumbs'][] = array(
       		'href'      => $this->url->link('extension/shipping', 'token=' . $this->session->data['token'], 'SSL'),
       		'text'      => $this->language->get('text_shipping'),
      		'separator' => ' :: '
   		);
   		$this->data['breadcrumbs'][] = array(
       		'href'      => $this->url->link('shipping/correios', 'token=' . $this->session->data['token'], 'SSL'),
       		'text'      => $this->language->get('heading_title'),
      		'separator' => ' :: '
   		);
		
   		$this->data['action'] = $this->url->link('shipping/correios', 'token=' . $this->session->data['token'], 'SSL');
		
   		$this->data['cancel'] = $this->url->link('extension/shipping', 'token=' . $this->session->data['token'], 'SSL');
		
		if (isset($this->request->post['correios_status'])) {
			$this->data['correios_status'] = $this->request->post['correios_status'];
		} else {
			$this->data['correios_status'] = $this->config->get('correios_status');
		}
		if (isset($this->request->post['correios_tax_class_id'])) {
			$this->data['correios_tax_class_id'] = $this->request->post['correios_tax_class_id'];
		} else {
			$this->data['correios_tax_class_id'] = $this->config->get('correios_tax_class_id');
		}
		if (isset($this->request->post['correios_geo_zone_id'])) {
			$this->data['correios_geo_zone_id'] = $this->request->post['correios_geo_zone_id'];
		} else {
			$this->data['correios_geo_zone_id'] = $this->config->get('correios_geo_zone_id');
		}
		if (isset($this->request->post['correios_postcode'])) {
			$this->data['correios_postcode'] = $this->request->post['correios_postcode'];
		} else {
			$this->data['correios_postcode'] = $this->config->get('correios_postcode');
		}
		
		if (isset($this->request->post['correios_mao_propria'])) {
			$this->data['correios_mao_propria'] = $this->request->post['correios_mao_propria'];
		} else {
			$this->data['correios_mao_propria'] = $this->config->get('correios_mao_propria');
		}
		if (isset($this->request->post['correios_aviso_recebimento'])) {
			$this->data['correios_aviso_recebimento'] = $this->request->post['correios_aviso_recebimento'];
		} else {
			$this->data['correios_aviso_recebimento'] = $this->config->get('correios_aviso_recebimento');
		}
		if (isset($this->request->post['correios_declarar_valor'])) {
			$this->data['correios_declarar_valor'] = $this->request->post['correios_declarar_valor'];
		} else {
			$this->data['correios_declarar_valor'] = $this->config->get('correios_declarar_valor');
		}
		if (isset($this->request->post['correios_adicional'])) {
			$this->data['correios_adicional'] = $this->request->post['correios_adicional'];
		} else {
			$this->data['correios_adicional'] = $this->config->get('correios_adicional');
		}
		if (isset($this->request->post['correios_prazo_adicional'])) {
			$this->data['correios_prazo_adicional'] = $this->request->post['correios_prazo_adicional'];
		} else {
			$this->data['correios_prazo_adicional'] = $this->config->get('correios_prazo_adicional');
		}		
		if (isset($this->request->post['correios_esedex_codigo'])) {
			$this->data['correios_esedex_codigo'] = $this->request->post['correios_esedex_codigo'];
		} else {
			$this->data['correios_esedex_codigo'] = $this->config->get('correios_esedex_codigo');
		}
		if (isset($this->request->post['correios_esedex_senha'])) {
			$this->data['correios_esedex_senha'] = $this->request->post['correios_esedex_senha'];
		} else {
			$this->data['correios_esedex_senha'] = $this->config->get('correios_esedex_senha');
		}						

		if (isset($this->request->post['correios_41106'])) {
			$this->data['correios_41106'] = $this->request->post['correios_41106'];
		} else {
			$this->data['correios_41106'] = $this->config->get('correios_41106');
		}
		
		if (isset($this->request->post['correios_40010'])) {
			$this->data['correios_40010'] = $this->request->post['correios_40010'];
		} else {
			$this->data['correios_40010'] = $this->config->get('correios_40010');
		}
		
		if (isset($this->request->post['correios_40045'])) {
			$this->data['correios_40045'] = $this->request->post['correios_40045'];
		} else {
			$this->data['correios_40045'] = $this->config->get('correios_40045');
		}
		
		if (isset($this->request->post['correios_40215'])) {
			$this->data['correios_40215'] = $this->request->post['correios_40215'];
		} else {
			$this->data['correios_40215'] = $this->config->get('correios_40215');
		}		

		if (isset($this->request->post['correios_81019'])) {
			$this->data['correios_81019'] = $this->request->post['correios_81019'];
		} else {
			$this->data['correios_81019'] = $this->config->get('correios_81019');
		}		
		
		if (isset($this->request->post['correios_sort_order'])) {
			$this->data['correios_sort_order'] = $this->request->post['correios_sort_order'];
		} else {
			$this->data['correios_sort_order'] = $this->config->get('correios_sort_order');
		}
		$this->load->model('localisation/tax_class');
		
		$this->data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
		
		$this->load->model('localisation/geo_zone');
		
		$this->data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		
		$this->template = 'shipping/correios.tpl';
		$this->children = array(
			'common/header',
			'common/footer'
		);
		
		$this->response->setOutput($this->render());
	}
	
	private function validate() {
		if (!$this->user->hasPermission('modify', 'shipping/correios')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		if (!preg_match ("/^([0-9]{2})\.?([0-9]{3})-?([0-9]{3})$/", $this->request->post['correios_postcode'])) {
			$this->error['postcode'] = $this->language->get('error_postcode');
		}
		if (!isset($this->request->post['correios_41106']) && !isset($this->request->post['correios_40010']) && !isset($this->request->post['correios_40045']) && !isset($this->request->post['correios_40215']) && !isset($this->request->post['correios_81019'])) {
			$this->error['servico'] = $this->language->get('error_servico');
		}		
		
		if (!$this->error) {
			return true;
		} else {
			return false;
		}	
	}
}
?>
