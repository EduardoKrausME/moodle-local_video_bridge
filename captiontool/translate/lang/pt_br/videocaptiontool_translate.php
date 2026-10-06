<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings de idioma.
 *
 * @package   videocaptiontool_translate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['description'] = 'Traduz legendas WebVTT com o AI Bridge e rejeita respostas que alterem os tempos dos cues.';
$string['pluginname'] = 'Traduzir legenda com IA';
$string['privacy:metadata'] = 'Esta ferramenta não armazena o conteúdo da legenda nem a resposta da IA. O AI Bridge registra seus próprios metadados de uso.';
$string['purposeidnumber'] = 'Purpose do AI Bridge';
$string['purposeidnumber_desc'] = 'Idnumber do purpose usado pelo local_ai_bridge para tradução de legendas.';
$string['targetlanguagerequired'] = 'É necessário informar o idioma de destino.';
$string['translationchangedtiming'] = 'A tradução da IA alterou uma ou mais linhas de tempo do WebVTT e, por segurança, o resultado foi rejeitado.';
