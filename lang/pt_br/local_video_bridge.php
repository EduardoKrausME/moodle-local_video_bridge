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
 * Brazilian Portuguese language strings for Video Bridge.
 *
 * @package   local_video_bridge
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['browservideonotsupported'] = 'Seu navegador não oferece suporte à reprodução de vídeo HTML5.';
$string['captionpluginmissing'] = 'A fonte de legenda "{$a}" não está instalada ou não está disponível.';
$string['captionsources'] = 'Fontes de legenda';
$string['captionsources_desc'] = 'Adaptadores compartilhados de fontes de legenda usados pelos plugins de vídeo do Moodle.';
$string['captiontoolmissing'] = 'A ferramenta de legenda "{$a}" não está instalada ou não está disponível.';
$string['captiontools'] = 'Ferramentas de legenda';
$string['captiontools_desc'] = 'Ferramentas compartilhadas de processamento de legendas usadas pelos plugins de vídeo do Moodle.';
$string['invalidcaptionplugin'] = 'A fonte de legenda "{$a}" é inválida e foi ignorada.';
$string['invalidcaptiontool'] = 'A ferramenta de legenda "{$a}" é inválida e foi ignorada.';
$string['invalidsourceplugin'] = 'A fonte de vídeo "{$a}" é inválida e foi ignorada.';
$string['pluginname'] = 'Video Bridge';
$string['privacy:metadata:progress'] = 'O Video Bridge armazena o progresso de reprodução de cada usuário para que qualquer plugin de vídeo possa reutilizar o mesmo mapa de visualização.';
$string['privacy:metadata:progress:component'] = 'O componente Moodle que está usando o vídeo.';
$string['privacy:metadata:progress:contextid'] = 'O contexto do módulo em que o vídeo foi exibido.';
$string['privacy:metadata:progress:currenttime'] = 'A última posição conhecida da reprodução.';
$string['privacy:metadata:progress:duration'] = 'A duração conhecida do vídeo.';
$string['privacy:metadata:progress:itemid'] = 'O ID da instância da atividade consumidora.';
$string['privacy:metadata:progress:map'] = 'A lista normalizada de trechos do vídeo efetivamente assistidos pelo usuário.';
$string['privacy:metadata:progress:mediahash'] = 'Um hash não reversível que identifica a mídia configurada dentro da atividade.';
$string['privacy:metadata:progress:percent'] = 'A porcentagem de trechos distintos efetivamente assistidos.';
$string['privacy:metadata:progress:source'] = 'A fonte do Video Bridge usada para reproduzir a mídia.';
$string['privacy:metadata:progress:timecreated'] = 'Quando o registro de progresso foi criado.';
$string['privacy:metadata:progress:timemodified'] = 'Quando o registro de progresso foi atualizado pela última vez.';
$string['privacy:metadata:progress:userid'] = 'O usuário cujo progresso de reprodução é armazenado.';
$string['privacy:progress'] = 'Progresso de reprodução do vídeo';
$string['sourcepluginmissing'] = 'A fonte de vídeo "{$a}" não está instalada ou não está disponível.';
$string['sources'] = 'Fontes de vídeo';
$string['sources_desc'] = 'Adaptadores compartilhados de fontes de vídeo usados pelos plugins de vídeo do Moodle.';
$string['subplugintype_videocaptionsource'] = 'Fonte de legenda';
$string['subplugintype_videocaptionsource_plural'] = 'Fontes de legenda';
$string['subplugintype_videocaptiontool'] = 'Ferramenta de legenda';
$string['subplugintype_videocaptiontool_plural'] = 'Ferramentas de legenda';
$string['subplugintype_videoprogresssource'] = 'Fonte de vídeo';
$string['subplugintype_videoprogresssource_plural'] = 'Fontes de vídeo';

$string['aibridgemissing'] = 'O local_ai_bridge é obrigatório para executar ferramentas de legenda com IA.';
$string['captiontoolinputtoolarge'] = 'A entrada enviada para a ferramenta de legenda é muito grande.';
$string['emptycaptiontoolinput'] = 'A entrada da ferramenta de legenda não pode estar vazia.';
$string['invalidmindmap'] = 'A resposta da IA não é um mindmap Mermaid válido.';
$string['invalidtoolwebvtt'] = 'A resposta da IA não é um WebVTT válido.';

$string['progressmap'] = 'Seu mapa de visualização';
$string['progresssaveerror'] = 'Não foi possível salvar o progresso do vídeo.';
