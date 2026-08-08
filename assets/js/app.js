/**
 * Agenda Escolar - Lógica JavaScript de Frontend
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Inicializar máscaras de telefone (IMask)
    const telInput = document.getElementById('telefone');
    const whatsappInput = document.getElementById('whatsapp');

    const maskOptions = {
        mask: [
            { mask: '(00) 0000-0000' },
            { mask: '(00) 00000-0000' }
        ]
    };

    if (telInput && typeof IMask !== 'undefined') {
        IMask(telInput, maskOptions);
    }
    if (whatsappInput && typeof IMask !== 'undefined') {
        IMask(whatsappInput, maskOptions);
    }

    // Copiar telefone para whatsapp se o usuário desejar
    const syncCheckbox = document.getElementById('mesmo_whatsapp');
    if (syncCheckbox && telInput && whatsappInput) {
        syncCheckbox.addEventListener('change', function() {
            if (this.checked) {
                whatsappInput.value = telInput.value;
                // Dispara evento para o IMask atualizar internamente se necessário
                whatsappInput.dispatchEvent(new Event('input'));
            } else {
                whatsappInput.value = '';
            }
        });
    }

    // 2. Formulário de Agendamento Multi-Step (Wizard)
    const wizardForm = document.getElementById('agendamentoForm');
    if (wizardForm) {
        const steps = Array.from(wizardForm.querySelectorAll('.step-content'));
        const progressBar = document.getElementById('progressBar');
        const stepIndicators = Array.from(document.querySelectorAll('.wizard-step'));
        const prevBtns = Array.from(wizardForm.querySelectorAll('.btn-prev'));
        const nextBtns = Array.from(wizardForm.querySelectorAll('.btn-next'));

        let currentStep = 0;

        // Atualizar estado visual do progresso
        function updateWizard() {
            steps.forEach((step, idx) => {
                if (idx === currentStep) {
                    step.classList.add('active');
                } else {
                    step.classList.remove('active');
                }
            });

            stepIndicators.forEach((indicator, idx) => {
                if (idx < currentStep) {
                    indicator.className = 'wizard-step completed';
                    indicator.innerHTML = '<i data-lucide="check" style="width:14px;height:14px;"></i>';
                } else if (idx === currentStep) {
                    indicator.className = 'wizard-step active';
                    indicator.textContent = idx + 1;
                } else {
                    indicator.className = 'wizard-step';
                    indicator.textContent = idx + 1;
                }
            });

            // Recriar ícones lucide dinâmicos nas etapas atualizadas
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Barra de progresso
            const percent = (currentStep / (steps.length - 1)) * 100;
            if (progressBar) {
                progressBar.style.width = percent + '%';
            }
        }

        // Eventos para Avançar
        nextBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                if (validateStep(currentStep)) {
                    currentStep++;
                    updateWizard();
                    window.scrollTo({ top: wizardForm.offsetTop - 100, behavior: 'smooth' });
                }
            });
        });

        // Eventos para Voltar
        prevBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                if (currentStep > 0) {
                    currentStep--;
                    updateWizard();
                    window.scrollTo({ top: wizardForm.offsetTop - 100, behavior: 'smooth' });
                }
            });
        });

        // Validar passo atual
        function validateStep(stepIndex) {
            if (stepIndex === 0) {
                // Passo 1: Seleção de data/horário
                const selectedHorario = wizardForm.querySelector('input[name="horario_id"]:checked');
                if (!selectedHorario) {
                    alert('Por favor, selecione um horário para a visita.');
                    return false;
                }
                return true;
            } else if (stepIndex === 1) {
                // Passo 2: Seleção de segmento
                const selectedSegmento = wizardForm.querySelector('input[name="segmento_id"]:checked');
                if (!selectedSegmento) {
                    alert('Por favor, selecione o segmento de interesse.');
                    return false;
                }
                return true;
            } else if (stepIndex === 2) {
                // Passo 3: Dados Pessoais (validação HTML5 nativa no submit final)
                const campos = Array.from(steps[2].querySelectorAll('[required]'));
                let valido = true;
                
                campos.forEach(campo => {
                    if (!campo.value.trim()) {
                        campo.classList.add('error');
                        valido = false;
                    } else {
                        campo.classList.remove('error');
                    }
                });

                if (!valido) {
                    alert('Por favor, preencha todos os campos obrigatórios.');
                }
                return valido;
            }
            return true;
        }

        // Seleção interativa dos cards de opção (horário/segmento)
        const optionCards = Array.from(wizardForm.querySelectorAll('.option-card'));
        optionCards.forEach(card => {
            card.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    // Desmarcar todos os outros rádios do mesmo grupo (mesmo nome)
                    const name = radio.name;
                    wizardForm.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                        r.checked = false;
                        const parent = r.closest('.option-card');
                        if (parent) parent.classList.remove('selected');
                    });
                    
                    radio.checked = true;
                    this.classList.add('selected');
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });
    }

    // 3. Controle Sidebar Admin Mobile
    const toggleBtn = document.getElementById('adminSidebarToggle');
    const sidebar = document.querySelector('.admin-sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
        
        // Fechar sidebar ao clicar fora em telas pequenas
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('open') && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    // 4. Fechamento automático de Toasts (Flash Messages)
    const toasts = Array.from(document.querySelectorAll('.toast'));
    toasts.forEach(toast => {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            toast.style.transition = 'all 0.5s ease';
            setTimeout(() => {
                toast.remove();
            }, 500);
        }, 5000); // 5 segundos
    });
});
