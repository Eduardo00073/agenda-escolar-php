<?php
/**
 * Agenda Escolar - Calendário de Agendamentos (FullCalendar)
 */
require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="margin-bottom: 30px;">
    <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 6px;">Calendário de Visitas</h2>
    <p style="color: var(--text-muted); font-size: 14px;">Visualize e acompanhe cronologicamente todos os agendamentos cadastrados no sistema.</p>
</div>

<!-- Área do Calendário -->
<div class="card" style="padding: 24px;">
    <div id="calendar" style="min-height: 600px;"></div>
</div>

<!-- MODAL DE DETALHES RÁPIDOS DO EVENTO -->
<div class="modal" id="modalEvento">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="font-size: 16px; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="info" size="18" style="color: var(--primary);"></i>
                Detalhes da Visita
            </h3>
            <button onclick="document.getElementById('modalEvento').classList.remove('open')" class="close-btn">
                <i data-lucide="x" size="20"></i>
            </button>
        </div>
        
        <div class="modal-body" style="font-size: 14px; display: flex; flex-direction: column; gap: 14px;">
            <div>
                <span class="form-label" style="margin-bottom: 2px;">Criança / Aluno(a)</span>
                <div id="evt_crianca" style="font-weight: 700; font-size: 16px; color: var(--text-main);"></div>
            </div>
            
            <div class="grid-2col">
                <div>
                    <span class="form-label" style="margin-bottom: 2px;">Responsável</span>
                    <div id="evt_responsavel" style="font-weight: 600;"></div>
                </div>
                <div>
                    <span class="form-label" style="margin-bottom: 2px;">Série de Interesse</span>
                    <div id="evt_serie" style="font-weight: 600;"></div>
                </div>
            </div>

            <div class="grid-2col">
                <div>
                    <span class="form-label" style="margin-bottom: 2px;">Horário</span>
                    <div id="evt_horario" style="font-weight: 600;"></div>
                </div>
                <div>
                    <span class="form-label" style="margin-bottom: 2px;">Status da Visita</span>
                    <div>
                        <span id="evt_status" class="badge"></span>
                    </div>
                </div>
            </div>
            
            <div class="grid-2col">
                <div>
                    <span class="form-label" style="margin-bottom: 2px;">Telefone / WhatsApp</span>
                    <div id="evt_contato" style="font-weight: 600;"></div>
                </div>
                <div>
                    <span class="form-label" style="margin-bottom: 2px;">E-mail</span>
                    <div id="evt_email" style="font-weight: 600;"></div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" onclick="document.getElementById('modalEvento').classList.remove('open')" class="btn btn-secondary">Fechar</button>
            <a href="#" id="evt_ver_ficha" class="btn btn-primary">
                <i data-lucide="external-link" size="14"></i> Ver Ficha Completa
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'pt-br',
        initialView: 'dayGridMonth',
        initialDate: new Date(), // Sempre começa na data atual
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
            today: 'Hoje',
            month: 'Mês',
            week: 'Semana',
            day: 'Dia'
        },
        editable: false,
        selectable: false,
        
        // URL da nossa API JSON para alimentar o FullCalendar
        events: 'api/agendamentos.php',
        
        // Após renderizar cada view — scroll para hoje
        viewDidMount: function(info) {
            // Em modos de tempo (semana/dia), rola para o horário de expediente (8h)
            if (info.view.type === 'timeGridWeek' || info.view.type === 'timeGridDay') {
                setTimeout(function() {
                    calendar.scrollToTime('08:00:00');
                }, 100);
            }

            // Em qualquer view, rolar a página para o dia de hoje no calendário
            setTimeout(function() {
                var hoje = calendarEl.querySelector('.fc-day-today');
                if (hoje) {
                    hoje.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    // Flash visual sutil
                    hoje.style.transition = 'box-shadow 0.4s ease';
                    hoje.style.boxShadow = '0 0 0 3px rgba(255,80,0,0.4)';
                    setTimeout(function() {
                        hoje.style.boxShadow = '';
                    }, 1500);
                }
            }, 300);
        },

        // Evento de Clique em Alguma Visita
        eventClick: function(info) {
            var evt = info.event;
            var props = evt.extendedProps;

            // Preencher campos do modal
            document.getElementById('evt_crianca').textContent = props.nome_crianca;
            document.getElementById('evt_responsavel').textContent = props.nome_responsavel;
            document.getElementById('evt_serie').textContent = props.nome_segmento + ' (' + props.serie_interesse + ')';
            
            // Formatando horário para exibição
            var dataFormatada = new Date(evt.start).toLocaleDateString('pt-BR');
            var horaInicio = new Date(evt.start).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
            var horaFim = new Date(evt.end).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
            document.getElementById('evt_horario').textContent = dataFormatada + ' de ' + horaInicio + ' às ' + horaFim;
            
            // Aplicar status badge dinâmico
            var statusEl = document.getElementById('evt_status');
            statusEl.textContent = props.status.toUpperCase();
            statusEl.className = 'badge'; // Reset
            
            if (props.status === 'pendente') statusEl.classList.add('badge-pending');
            else if (props.status === 'confirmado') statusEl.classList.add('badge-confirmed');
            else if (props.status === 'realizado') statusEl.classList.add('badge-completed');
            else if (props.status === 'cancelado') statusEl.classList.add('badge-cancelled');

            document.getElementById('evt_contato').textContent = props.telefone + ' / ' + props.whatsapp;
            document.getElementById('evt_email').textContent = props.email;
            
            // Vincular link da ficha
            document.getElementById('evt_ver_ficha').href = 'agendamento-detalhes.php?id=' + evt.id;

            // Abrir Modal
            document.getElementById('modalEvento').classList.add('open');
            
            // Recriar ícones lucide se precisar no modal
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    });
    
    calendar.render();

    // Fechar modal ao clicar fora
    const modal = document.getElementById('modalEvento');
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
        }
    });
});
</script>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
?>
