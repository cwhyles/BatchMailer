<?php
/* 
 * BatchMailer – Controlled batch email module for Dolibarr
 *
 * Copyright (C) 2025–2026 Colin Whyles
 *
 * This file is part of the BatchMailer module for Dolibarr ERP/CRM.
 *
 * BatchMailer is designed to provide safe, auditable, batch-based
 * email sending using CSV recipient lists and reusable templates.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * @package BatchMailer
 */

include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';

/**
 * Class modBatchMailer
 *
 * Module descriptor for the BatchMailer module.
 */

class modBatchMailer extends DolibarrModules
{
    public function __construct($db)
    {
        global $conf;

        $this->db = $db;

        $this->numero = 500000; // Pick a high unused number
        $this->module_position = 500;
        $this->rights_class = 'batchmailer';
        $this->family = 'tools';
        $this->name = 'batchmailer';
        $this->description = 'Safe, throttled batch email sender for administrative use';
        $this->version = '0.1.0';
        $this->editor_name = 'Colin Whyles';
        $this->editor_url = 'https://github.com/cwhyles';

        $this->const_name = 'MAIN_MODULE_BATCHMAILER';
        $this->picto = 'email';

        $this->module_parts = array(
            'triggers' => 0,
            'login' => 0,
            'substitutions' => 0
        );

		$this->dirs = array(
			"/batchmailer",
			"/batchmailer/csv",
			"/batchmailer/logs",
			"/batchmailer/templates"
		);
        $this->config_page_url = array('setup.php@batchmailer');

        $this->depends = array();
        $this->requiredby = array();
        $this->langfiles = array('batchmailer@batchmailer');

        $this->phpmin = array(7,4);
        $this->need_dolibarr_version = array(20,0);

        // Permissions
        $this->rights = array();
        $r = 1;
        
        $this->rights[$r][0] = 500001;
        $this->rights[$r][1] = 'Run batch mailer';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'run';
        
        $r++;
        
        $this->rights[$r][0] = 500002;
        $this->rights[$r][1] = 'Administer batch mailer templates';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'admin';
        
        $this->hidden = false;

        // Menu
        $this->menu = array();
        $this->menu[] = array(
            'fk_menu' => 'fk_mainmenu=tools',
            'type' => 'left',
            'titre' => 'BatchMailerName',
            'mainmenu' => 'tools',
            'leftmenu' => 'batchmailer',
            'url' => '/batchmailer/batchmailer.php',
            'langs' => 'batchmailer@batchmailer',
            'position' => 100,
            'enabled' => '$conf->batchmailer->enabled',
            'perms' => '$user->rights->batchmailer->run',
            'target' => '',
            'user' => 2
        );

        $this->menu[] = array(
            'fk_menu'  => 'fk_mainmenu=tools,fk_leftmenu=batchmailer',
            'type'     => 'left',
            'titre'    => 'BatchMailerTemplates',
            'mainmenu' => 'tools',
            'leftmenu' => 'batchmailer_templates',
            'url'      => '/batchmailer/template_editor.php',
            'langs'    => 'batchmailer@batchmailer',
            'position' => 110,
            'enabled'  => '$conf->batchmailer->enabled',
            'perms'    => '$user->rights->batchmailer->admin',
            'target'   => '',
            'user'     => 2
        );
    }
}
?>