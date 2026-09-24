@php $it = $it ?? []; @endphp
<tr class="oc-fila">
    <td><input type="text" name="item_descripcion[]" class="form-control form-control-sm" value="{{ $it['descripcion'] ?? '' }}" aria-label="Descripción" placeholder="Ej.: parche 12&quot; Remo"></td>
    <td><input type="text" name="item_tipo[]" class="form-control form-control-sm" value="{{ $it['tipo'] ?? '' }}" aria-label="Tipo" placeholder="instrumento"></td>
    <td><input type="text" name="item_familia[]" class="form-control form-control-sm" value="{{ $it['familia'] ?? '' }}" aria-label="Familia" placeholder="Repique"></td>
    <td><input type="text" name="item_marca[]" class="form-control form-control-sm" value="{{ $it['marca'] ?? '' }}" aria-label="Marca"></td>
    <td><input type="text" name="item_modelo[]" class="form-control form-control-sm" value="{{ $it['modelo'] ?? '' }}" aria-label="Modelo"></td>
    <td><input type="text" name="item_medida[]" class="form-control form-control-sm" value="{{ $it['medida'] ?? '' }}" aria-label="Medida"></td>
    <td><input type="number" name="item_cantidad[]" class="form-control form-control-sm oc-cant" step="0.01" min="0" value="{{ $it['cantidad'] ?? 1 }}" aria-label="Cantidad"></td>
    <td><input type="text" name="item_unidad[]" class="form-control form-control-sm" value="{{ $it['unidad'] ?? 'u' }}" aria-label="Unidad"></td>
    <td><input type="number" name="item_precio[]" class="form-control form-control-sm oc-precio" step="0.01" min="0" value="{{ $it['precio'] ?? '' }}" aria-label="Precio unitario"></td>
    <td class="text-end">
        <button type="button" class="btn btn-sm btn-ghost text-danger btn-remove-row" aria-label="Quitar ítem"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </td>
</tr>
