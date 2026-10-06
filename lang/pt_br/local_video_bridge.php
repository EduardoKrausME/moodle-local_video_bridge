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

$string['aibridgemissing'] = 'O local_ai_bridge é obrigatório para executar ferramentas de legenda com IA.';
$string['browservideonotsupported'] = 'Seu navegador não oferece suporte à reprodução de vídeo HTML5.';
$string['captionpluginmissing'] = 'A fonte de legenda "{$a}" não está instalada ou não está disponível.';
$string['captionsources'] = 'Fontes de legenda';
$string['captionsources_desc'] = 'Adaptadores compartilhados de fontes de legenda usados pelos plugins de vídeo do Moodle.';
$string['captiontoolinputtoolarge'] = 'A entrada enviada para a ferramenta de legenda é muito grande.';
$string['captiontoolmissing'] = 'A ferramenta de legenda "{$a}" não está instalada ou não está disponível.';
$string['captiontools'] = 'Ferramentas de legenda';
$string['captiontools_desc'] = 'Ferramentas compartilhadas de processamento de legendas usadas pelos plugins de vídeo do Moodle.';
$string['emptycaptiontoolinput'] = 'A entrada da ferramenta de legenda não pode estar vazia.';
$string['invalidcaptionplugin'] = 'A fonte de legenda "{$a}" é inválida e foi ignorada.';
$string['invalidcaptiontool'] = 'A ferramenta de legenda "{$a}" é inválida e foi ignorada.';
$string['invalidmindmap'] = 'A resposta da IA não é um mindmap Mermaid válido.';
$string['invalidsourceplugin'] = 'A fonte de vídeo "{$a}" é inválida e foi ignorada.';
$string['invalidtoolwebvtt'] = 'A resposta da IA não é um WebVTT válido.';
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

$string['privacy:metadata:session'] = 'O Video Bridge armazena telemetria compacta das sessões de reprodução para consumidores de analytics.';
$string['privacy:metadata:session:source'] = 'O provider usado para reproduzir o vídeo.';
$string['privacy:metadata:session:level'] = 'O nível de detalhamento da telemetria armazenada para a sessão.';
$string['privacy:metadata:session:duration'] = 'A duração da mídia informada para a sessão.';
$string['privacy:metadata:session:plays'] = 'A quantidade de eventos de reprodução.';
$string['privacy:metadata:session:pauses'] = 'A quantidade de pausas.';
$string['privacy:metadata:session:seeks'] = 'A quantidade de seeks detectados.';
$string['privacy:metadata:session:replays'] = 'A quantidade de seeks para trás associados a replay.';
$string['privacy:metadata:session:skips'] = 'A quantidade de seeks para frente associados a skip.';
$string['privacy:metadata:session:dropoff'] = 'A última posição de reprodução quando a sessão terminou.';
$string['privacy:metadata:session:maxposition'] = 'A posição mais distante alcançada no vídeo.';
$string['privacy:metadata:session:speedavg'] = 'A velocidade média de reprodução utilizada.';
$string['privacy:metadata:session:pausepoints'] = 'As posições da timeline em que ocorreram pausas.';
$string['privacy:metadata:session:skippoints'] = 'Os intervalos de seek para frente.';
$string['privacy:metadata:session:replaypoints'] = 'Os intervalos reproduzidos novamente após seek para trás.';
$string['privacy:metadata:session:rates'] = 'As velocidades de reprodução e seus tempos observados.';
$string['privacy:metadata:session:continuousblocks'] = 'Os blocos compactos de reprodução contínua.';
$string['privacy:metadata:session:inactivitygaps'] = 'Os intervalos de inatividade detectados na sessão.';
$string['privacy:metadata:session:timecreated'] = 'Quando o registro da sessão foi criado.';
$string['privacy:metadata:session:component'] = 'O componente Moodle que está usando o vídeo.';
$string['privacy:metadata:session:contextid'] = 'O contexto do módulo em que o vídeo foi exibido.';
$string['privacy:metadata:session:endedat'] = 'Quando a sessão de reprodução terminou.';
$string['privacy:metadata:session:itemid'] = 'O ID da instância da atividade consumidora.';
$string['privacy:metadata:session:mediahash'] = 'O identificador não reversível da mídia.';
$string['privacy:metadata:session:ranges'] = 'Os intervalos compactados assistidos quando a telemetria detalhada está habilitada.';
$string['privacy:metadata:session:sessionid'] = 'Um identificador aleatório da sessão de reprodução.';
$string['privacy:metadata:session:startedat'] = 'Quando a sessão de reprodução começou.';
$string['privacy:metadata:session:timemodified'] = 'Quando o snapshot compacto da sessão foi atualizado.';
$string['privacy:metadata:session:userid'] = 'O usuário cuja sessão de reprodução é armazenada.';
$string['privacy:metadata:session:watchtime'] = 'O tempo real estimado de reprodução na sessão.';
$string['privacy:sessions'] = 'Sessões de reprodução do vídeo';

$string['privacy:progress'] = 'Progresso de reprodução do vídeo';
$string['progressmap'] = 'Seu mapa de visualização';
$string['progresssaveerror'] = 'Não foi possível salvar o progresso do vídeo.';
$string['sourcepluginmissing'] = 'A fonte de vídeo "{$a}" não está instalada ou não está disponível.';
$string['sources'] = 'Fontes de vídeo';
$string['sources_desc'] = 'Adaptadores compartilhados de fontes de vídeo usados pelos plugins de vídeo do Moodle.';
$string['subplugintype_videocaptionsource'] = 'Fonte de legenda';
$string['subplugintype_videocaptionsource_plural'] = 'Fontes de legenda';
$string['subplugintype_videocaptiontool'] = 'Ferramenta de legenda';
$string['subplugintype_videocaptiontool_plural'] = 'Ferramentas de legenda';
$string['subplugintype_videoprogresssource'] = 'Fonte de vídeo';
$string['subplugintype_videoprogresssource_plural'] = 'Fontes de vídeo';

$string['eventanalyticsupdated'] = 'Analytics de vídeo atualizados';

$string['privacy:metadata:session:events'] = 'Eventos compactos e ordenados de reprodução registrados para a sessão.';

$string['privacy:metadata:session:sessionduration'] = 'Duração real da sessão de reprodução.';
$string['privacy:metadata:session:pausedtime'] = 'Tempo pausado observado durante a sessão.';
$string['privacy:metadata:session:startposition'] = 'Posição do vídeo em que a sessão começou.';
$string['privacy:metadata:session:endposition'] = 'Última posição do vídeo observada na sessão.';
$string['privacy:metadata:session:percentstart'] = 'Percentual autoritativo assistido no início da sessão.';
$string['privacy:metadata:session:percentend'] = 'Percentual autoritativo assistido no fim da sessão.';
$string['privacy:metadata:session:ratechanges'] = 'Quantidade de mudanças de velocidade observadas.';
$string['privacy:metadata:session:receivedended'] = 'Se o player emitiu o evento ended.';
$string['privacy:metadata:session:endreason'] = 'Motivo descritivo normalizado do encerramento da sessão.';
