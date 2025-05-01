<div class="container-fluid mb-5 bg-white">
    <div class="row justify-content-center pb-5">
        <div class="col-12 p-4">

            <div class="row">
                <div class="col">
                    <h4><strong>Dados estatísticos</strong></h4>
                </div>
                <div class="col text-end">
                    <a href="?ct=main&mt=index" class="btn btn-secondary px-4"><i class="fa-solid fa-chevron-left me-2"></i>Voltar</a>
                </div>
            </div>

            <hr>

            <div class="row mb-3">
                <div class="col-sm-6 col-12 p-1">
                    <div class="card p-3">
                        <h4><i class="fa-solid fa-users me-2"></i>Clientes dos agentes</h4>

                        <?php if (count($agents) == 0) : ?>
                            <p class="text-center">Não foram encontrados dados.</p>
                        <?php else : ?>
                            <table class="table table-striped table-bordered" id="tb_agents">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Agente</th>
                                        <th class="text-center">Clientes registrados</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($agents as $agent) : ?>
                                        <tr>
                                            <th><?= $agent->agente ?></th>
                                            <th class="text-center"><?= $agent->total_clientes ?></th>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                    </div>
                </div>
                <div class="col-sm-6 col-12 p-1">
                    <div class="card p-3">
                        <h4><i class="fa-solid fa-users me-2"></i>Gráfico</h4>
                        <canvas id="chartjs_chart" height="300px"></canvas>
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col p-1">

                    <div class="card p-3">
                        <h4><i class="fa-solid fa-chart-pie me-2"></i>Dados estatísticos globais</h4>
                        <div class="row justify-content-center">
                            <div class="col-10">
                                <canvas id="globalStatsChart" height="100px"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-3">
                        <a href="?ct=admin&mt=create_pdf_report" target="_blank" class="btn btn-secondary px-4"><i class="fa-solid fa-file-pdf me-2"></i>Gerar relatório em PDF</a>
                    </div>

                </div>
            </div>

            <div class="row mb-3">
                <div class="col text-center">
                    <a href="?ct=main&mt=index" class="btn btn-secondary px-4"><i class="fa-solid fa-chevron-left me-2"></i>Voltar</a>
                </div>
            </div>

        </div>
    </div>
</div>
</div>

<script>
    $(document).ready(function() {
        // datatable
        $("#tb_agents").DataTable({
            pageLength: 10,
            pagingType: "full_numbers",
            language: {
                decimal: "",
                emptyTable: "Sem dados disponíveis na tabela",
                info: "Mostrando _START_ até _END_ de _TOTAL_ registros",
                infoEmpty: "Mostrando 0 até 0 de 0 registros",
                infoFillered: "(Filtrando _MAX_ total de registros)",
                infoPostFix: "",
                thousands: ",",
                lengthMenu: "Mostrando _MENU_ registros por página",
                loadingRecords: "Carregando...",
                processing: "Processando...",
                search: "Filtrar:",
                zeroRecords: "Nenhum registro encontrado",
                paginate: {
                    first: "Primeira",
                    last: "Última",
                    next: "Próxima",
                    previous: "Anterior"
                },
                aria: {
                    sortAscending: ": ative para classificar a coluna em ordem crescente.",
                    sortAscending: ": ative para classificar a coluna em ordem crescente."
                }
            }
        });
    })

    // chart js
    <?php if (count($agents) != 0) : ?>

        new Chart(
            document.querySelector('#chartjs_chart'), {
                type: 'bar',
                data: {
                    labels: <?= $chart_labels ?>,
                    datasets: [{
                        label: 'Total de clientes por agente',
                        data: <?= $chart_totals ?>,
                        backgroundColor: 'rgb(50,100,200)',
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                },
            }
        )

        const ctx = document.getElementById('globalStatsChart').getContext('2d');

        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [
                    'Total de agentes',
                    'Total de clientes',
                    'Clientes inativos',
                    'Clientes por agente',
                    'Idade mais nova',
                    'Idade mais velha',
                    'Média de idades',
                    'Homens (%)',
                    'Mulheres (%)'
                ],
                datasets: [{
                    label: 'Estatísticas',
                    data: [
                        <?= $global_stats['total_agents']->value ?>,
                        <?= $global_stats['total_clients']->value ?>,
                        <?= $global_stats['total_deleted_clients']->value ?>,
                        <?= sprintf("%d", $global_stats['average_clients_per_agent']->value) ?>,
                        <?= empty($global_stats['younger_client']->value) ? 0 : $global_stats['younger_client']->value ?>,
                        <?= empty($global_stats['oldest_client']->value) ? 0 : $global_stats['oldest_client']->value ?>,
                        <?= sprintf("%.2f", $global_stats['average_age']->value) ?>,
                        <?= sprintf("%.2f", $global_stats['percentage_males']->value) ?>,
                        <?= sprintf("%.2f", $global_stats['percentage_females']->value) ?>
                    ],
                    backgroundColor: [
                        '#007bff', '#28a745', '#6c757d',
                        '#17a2b8', '#ffc107', '#dc3545',
                        '#fd7e14', '#6610f2', '#20c997'
                    ],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.raw;
                                if (label.includes('%')) {
                                    return `${label}: ${value.toFixed(2)}%`;
                                }
                                return `${label}: ${value}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    <?php endif; ?>
</script>