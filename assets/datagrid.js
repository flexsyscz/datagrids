import naja from "naja/dist/Naja";

export default function () {
	document.querySelectorAll('.datagrid').forEach((datagrid) => {
		let count = datagrid.querySelector('#numberOfSelectedRows')
		let currentCount = parseInt(count.textContent)

		datagrid.querySelectorAll('.process-selection-btn').forEach((processSelectionBtn) => {
			if (currentCount > 0) {
				processSelectionBtn.classList.remove('disabled')
			} else {
				processSelectionBtn.classList.add('disabled')
			}
		})
	})

	document.querySelectorAll('[id^="_dgRow_"]').forEach((checkbox) => {
		checkbox.addEventListener('change', () => {
			let url = checkbox.dataset.fxsUrl
			if (url) {
				naja.makeRequest('POST', url, {state: checkbox.checked}, {history: false}).then((payload) => {
					let root = checkbox.closest('.datagrid')
					let count = root.querySelector('#numberOfSelectedRows')
					let classList = checkbox.closest('tr').classList
					let currentCount = parseInt(count.textContent)

					if (payload.selected) {
						classList.add('table-success')
						if (count) {
							count.textContent = (currentCount + 1).toString()
						}
					} else {
						classList.remove('table-success')
						if (count) {
							count.textContent = (currentCount - 1).toString()
						}
					}

					let processSelectionBtn = root.querySelector('.process-selection-btn')
					if (processSelectionBtn) {
						if (parseInt(count.textContent) > 0) {
							processSelectionBtn.classList.remove('disabled')
						} else {
							processSelectionBtn.classList.add('disabled')
						}
					}
				}).catch((err) => {
					alert(err)
				})
			}
		})
	})

	document.querySelector('[id="_dgAllRows"]')?.addEventListener('change', (e) => {
		let input = e.currentTarget
		let url = input.dataset.fxsUrl
		if (url) {
			naja.makeRequest('POST', url, {state: input.checked}, {history: false}).then((payload) => {
				let root = input.closest('.datagrid')
				let count = root.querySelector('#numberOfSelectedRows')

				if (count) {
					count.textContent = payload.count
				}

				let processSelectionBtn = root.querySelector('.process-selection-btn')
				if (processSelectionBtn) {
					if (parseInt(count.textContent) > 0) {
						processSelectionBtn.classList.remove('disabled')
					} else {
						processSelectionBtn.classList.add('disabled')
					}
				}

				root.querySelectorAll('tbody tr').forEach(row => {
					row.querySelector('[id^="_dgRow_"]').checked = input.checked

					if (payload.count) {
						row.classList.add('table-success')
					} else {
						row.classList.remove('table-success')
					}
				})
			}).catch((err) => {
				alert(err)
			})
		}
	})
}
