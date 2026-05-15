<template>
    <div class="min-h-screen bg-gray-900 text-gray-100">
        <div v-if="loading" class="flex items-center justify-center min-h-screen">
            <span class="text-gray-500">Loading...</span>
        </div>

        <template v-else>
            <ul class="flex justify-between gap-5 p-5 flex-wrap border-b border-gray-700">
                <li v-for="total in totals" :key="total.name" class="text-center">
                    <div class="text-xs md:text-lg text-gray-400">{{ total.name }}</div>
                    <div class="text-lg font-bold md:text-2xl lg:text-5xl text-white">
                        &pound;{{ formatAmount(total.total) }}
                    </div>
                </li>
            </ul>

            <table class="w-full text-sm md:text-xl">
                <thead>
                    <tr class="text-gray-400">
                        <td class="p-3 border-b border-gray-700 font-bold">Who</td>
                        <td class="p-3 border-b border-gray-700 font-bold">Description</td>
                        <td class="p-3 border-b border-gray-700 font-bold">Regular Amount</td>
                        <td class="p-3 border-b border-gray-700 font-bold">Current Amount</td>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="source in sources"
                        :key="source.id"
                        class="hover:bg-gray-800/50 transition-colors"
                    >
                        <td class="p-3 border-b border-gray-800">{{ source.who }}</td>
                        <td class="p-3 border-b border-gray-800">{{ source.description }}</td>
                        <td class="p-3 border-b border-gray-800">
                            <div class="flex w-full items-center">
                                <span class="py-1 text-gray-500">&pound;</span>
                                <input
                                    type="number"
                                    :value="source.regular_amount"
                                    @change="updateField(source.id, 'regular', $event.target.value)"
                                    class="p-1 grow max-w-16 md:max-w-none bg-transparent border-b border-gray-700 focus:border-blue-500 focus:outline-none text-gray-100"
                                />
                            </div>
                        </td>
                        <td class="p-3 border-b border-gray-800">
                            <div class="flex w-full items-center">
                                <span class="py-1 text-gray-500">&pound;</span>
                                <input
                                    type="number"
                                    :value="source.current_amount"
                                    @change="updateField(source.id, 'current', $event.target.value)"
                                    class="p-1 grow max-w-16 md:max-w-none bg-transparent border-b border-gray-700 focus:border-blue-500 focus:outline-none text-gray-100"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const sources = ref([])
const totals = ref([])
const loading = ref(true)

function formatAmount(amount) {
    return new Intl.NumberFormat('en-GB').format(amount)
}

async function updateField(id, name, value) {
    await axios.post('/wealth/update', { id, name, value })

    const source = sources.value.find(s => s.id === id)
    if (name === 'current') {
        source.current_amount = parseInt(value)
        const response = await axios.get('/wealth/data')
        totals.value = response.data.totals
    } else {
        source.regular_amount = parseInt(value)
    }
}

onMounted(async () => {
    const response = await axios.get('/wealth/data')
    sources.value = response.data.sources
    totals.value = response.data.totals
    loading.value = false
})
</script>
