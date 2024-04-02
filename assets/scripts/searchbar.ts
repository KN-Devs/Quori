import { createApp } from "vue";

createApp({ 
    compilerOptions: {  
        delimiters: ["${", "}$"]
    },

    data() {
        return {
            timeout: null,
            isLoading: false, 
            questions: null
        }
    },

    methods: {
        updateInput(event: KeyboardEvent) {
            clearTimeout(this.timeout);
            this.timeout = setTimeout(async() => {  
                this.isLoading = true;
                try {
                    const response = await fetch(`/question/search/${ this.$refs.input.value }`);
                    const body = await response.json();
                    this.questions = JSON.parse(body);
                    this.isLoading = false
                    console.log(body);
                } catch (error) {
                    this.isLoading = false;
                    this.questions = null;
                }
                console.log(this.$refs.input.value);
            }, 1000);
        }
    }

}).mount('#search');