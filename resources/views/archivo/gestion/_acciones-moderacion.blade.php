{{-- Aprobar, pedir cambios o rechazar un aporte. $foto · $volver (opcional) --}}
<div class="agx-acciones-mod">
    <form method="POST" action="{{ route('archivo.gestion.fotos.estado', $foto) }}">
        @csrf
        <input type="hidden" name="volver" value="{{ $volver ?? url()->current() }}">
        <button class="btn btn-success btn-sm" name="accion" value="aprobar"><i class="bi bi-check-lg"></i> Aprobar y publicar</button>
    </form>
    @if($foto->estado !== 'cambios')
        <details class="agx-acciones-mod__mas">
            <summary class="btn btn-outline-warning btn-sm">Pedir cambios</summary>
            <form method="POST" action="{{ route('archivo.gestion.fotos.estado', $foto) }}">
                @csrf
                <input type="hidden" name="volver" value="{{ $volver ?? url()->current() }}">
                <label class="form-label small" for="cambios-{{ $foto->id }}">Qué necesitás que complete</label>
                <textarea class="form-control form-control-sm" id="cambios-{{ $foto->id }}" name="notas" rows="3" required placeholder="Necesitamos más información sobre esta fotografía. Por favor indicá: año aproximado, lugar, quiénes aparecen."></textarea>
                <button class="btn btn-warning btn-sm mt-2" name="accion" value="cambios">Enviar pedido</button>
            </form>
        </details>
    @endif
    @if($foto->estado !== 'rechazada')
        <details class="agx-acciones-mod__mas">
            <summary class="btn btn-outline-danger btn-sm">Rechazar</summary>
            <form method="POST" action="{{ route('archivo.gestion.fotos.estado', $foto) }}">
                @csrf
                <input type="hidden" name="volver" value="{{ $volver ?? url()->current() }}">
                <label class="form-label small" for="rechazo-{{ $foto->id }}">Motivo (lo ve quien la aportó)</label>
                <textarea class="form-control form-control-sm" id="rechazo-{{ $foto->id }}" name="notas" rows="2" required></textarea>
                <button class="btn btn-danger btn-sm mt-2" name="accion" value="rechazar">Rechazar</button>
            </form>
        </details>
    @endif
</div>
