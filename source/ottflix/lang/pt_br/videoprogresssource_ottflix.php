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
 * Shared video source.
 *
 * @package   videoprogresssource_ottflix
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['baseurl'] = 'URL do OTTFlix';
$string['baseurl_desc'] = 'URL base da instalação OTTFlix usada para solicitar players protegidos.';
$string['configurationmissing'] = 'Configure a URL e o token da API do OTTFlix antes de usar esta fonte.';
$string['invalidurl'] = 'Informe um identificador ou link válido de um conteúdo OTTFlix.';
$string['ottflixurl'] = 'Link ou identificador OTTFlix';
$string['playererror'] = 'O OTTFlix não conseguiu retornar o player deste conteúdo.';
$string['pluginname'] = 'OTTFlix';
$string['privacy:metadata:ottflix'] = 'O OTTFlix recebe informações do usuário necessárias para emitir o player protegido.';
$string['privacy:metadata:ottflix:enrollment'] = 'O identificador do módulo usado como referência de matrícula.';
$string['privacy:metadata:ottflix:student_email'] = 'O e-mail do aluno enviado ao serviço de player protegido.';
$string['privacy:metadata:ottflix:student_name'] = 'O nome completo do aluno enviado ao serviço de player protegido.';
$string['privacy:metadata:ottflix:userid'] = 'O identificador do usuário Moodle usado como referência de segurança do player.';
$string['token'] = 'Token da API do OTTFlix';
$string['token_desc'] = 'Token enviado no cabeçalho Authorization quando o Video Bridge solicita o player.';
