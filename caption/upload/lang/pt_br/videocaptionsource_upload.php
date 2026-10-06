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
 * Strings de idioma para legendas enviadas.
 *
 * @package   videocaptionsource_upload
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['captionfiles'] = 'Arquivos de legenda';
$string['captionfiles_help'] = 'Envie um ou mais arquivos WebVTT (.vtt) ou SubRip (.srt). Arquivos SRT são convertidos para WebVTT. Use nomes como pt-BR.vtt, pt-BR__Português.vtt ou pt-BR__Português.default.vtt para definir o idioma, o rótulo e a legenda padrão.';
$string['captionlabel'] = 'Legenda';
$string['filetoolarge'] = 'Cada arquivo de legenda deve ter no máximo 5 MB.';
$string['invalidextension'] = 'Somente arquivos de legenda VTT e SRT são aceitos.';
$string['invalidvtt'] = 'O arquivo de legenda não contém cues WebVTT válidos.';
$string['pluginname'] = 'Upload de legendas';
$string['privacy:metadata'] = 'A fonte de upload armazena os arquivos de legenda no contexto da atividade consumidora e não armazena dados pessoais de forma independente.';
